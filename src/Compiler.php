<?php

namespace RiotPhpTags;

/**
 * Riot Tag Compiler
 *
 */

class Compiler
{
    protected $settings;

    const defaultBrackets = '{ }';

    public function __construct(array $settings = [])
    {
        $this->settings = array_merge([
            'brackets' => self::defaultBrackets,
            'store-tag-scripts' => false,
            'store-tag-styles' => true,
            'transform-yield' => true,
            'css-preprocessors' => [],
        ], $settings);

        $this->settings['brackets'] = explode(' ', $this->settings['brackets']);
    }

    /**
     * Transforms Riot expressions into PHP on a tag object
     *
     * @param object $node simple_html_dom_node
     * @return void
     */
    public function transformExpressions(\simple_html_dom_node &$node)
    {
        if ($node->nodetype === 3) {
            $node->innertext = $this->phpifyExpression($node->text());

            return;
        }

        $attrs_to_delete = [];

        foreach ($node->nodes as $i => $child) {
            $this->transformExpressions($child);

            $surround = [];

            foreach ($child->attr as $child_attr => $child_attr_value) {
                // Remove on{event} attributes
                if (preg_match('|on\w+|', $child_attr)) {
                    $attrs_to_delete[] = $child_attr;
                }

                // Find control attributes
                foreach (['each', 'if'] as $ctrl) {
                    if (strstr($child_attr, $ctrl)) {
                        $surround[$ctrl] = $child_attr_value;
                        $attrs_to_delete[] = $child_attr;
                    }
                }

                if (!in_array($child_attr, ['show', 'hide', 'each', 'if']) && strstr($child_attr_value, $this->settings['brackets'][0])) {
                    $child->attr[$child_attr] = $this->phpifyExpression($child_attr_value);
                }
            }

            $display = [];

            foreach (['show', 'hide'] as $ctrl) {
                if (isset($child->{$ctrl})) {
                    // Only last one should matter in case somebody puts both show and hide controls on a tag
                    $display = [
                        'action' => $ctrl === 'show' ? true : false,
                        'expression' => $child->{$ctrl}
                    ];
                    $attrs_to_delete[] = $ctrl;
                }
            }

            if (!empty($display)) {
                $style = isset($child->style) ? $child->style : '';

                if (!preg_match('|\bdisplay\b\s*:|', $style, $m)) {
                    $style = 'display: initial;';
                }

                /**/
                $style = preg_replace(
                    '|(\bdisplay\b\s*:)[^;]*(;?)|',
                    '$1 <?php if ('.$this->phpifyExpression($display['expression'], 'if').') { ?>'.($display['action'] ? 'initial' : 'none').'<?php } ?>$2',
                    $style
                );
                /**/

                $style = trim(str_replace('display: initial', '', $style), ' ;');

                if (!empty($style)) {
                    $child->style = $style;
                }
            }

            foreach ($attrs_to_delete as $attr) {
                $child->removeAttribute($attr);
            }

            if (!empty($surround)) {
                foreach ($surround as $ctrl => $expression) {
                    switch ($ctrl) {
                        case 'each':
                            $child->outertext = "\n".'<?php foreach ('.$this->phpifyExpression($expression, $ctrl).') { ?>'."\n".$child."\n".'<?php } ?>'."\n";
                            break;
                        case 'if':
                            $child->outertext = "\n".'<?php if ('.$this->phpifyExpression($expression, $ctrl).') { ?>'."\n".$child."\n".'<?php } ?>'."\n";
                            break;
                    }
                }
            }
        }
    }

    /**
     * Transform HTML tag
     *
     * @param string $tag HTML string
     * @return array
     *
     */
    public function transform($tag)
    {
        // Fixes riotjs attributes without quotes
        $tag = preg_replace('|([a-z]+=)(\{.+?\})|ms', '$1"$2"', $tag);
        $tag = $this->maskTextContentTag($tag);

        //str_get_html($str, $lowercase=true, $forceTagsClosed=true, $target_charset = DEFAULT_TARGET_CHARSET, $stripRN=true)
        $RiotTags = new \simple_html_dom;
        // ->load($str, $lowercase, $stripRN);
        $RiotTags->load($tag, true, false);
        $RiotTags = $RiotTags->root->nodes;

        // $mixins = [];
        $styles    = [];
        $scripts   = [];
        $tags      = [];
        $riot_tags = [];

        echo "(".count($RiotTags)."):\n";

        foreach ($RiotTags as $RiotTag) {
            echo "  {$RiotTag->tag} ={$RiotTag->nodetype}\n";
            $RiotTag_tag = ltrim($this->unmaskTextContentTag('<'.$RiotTag->tag), '<');

            $tags[$RiotTag_tag] = (string) $this->unmaskTextContentTag($RiotTag);

            $riot_last_text_script_checked = false;

            for ($i = count($RiotTag->nodes) -1; $i >= 0; $i--) {
                $node = $RiotTag->nodes[$i];

                // Extract last text node as Riot Script
                if (!$riot_last_text_script_checked && $node->tag === 'text') {
                    $node_text = $node->text();

                    if (strlen(trim($node_text))) {
                        // Do not check any text nodes
                        $riot_last_text_script_checked = true;
                        // Remember the script contents
                        $scripts[$RiotTag_tag][] = [
                            'text' => (string) $node_text
                        ];

                        // Replace script text with new line
                        $RiotTag->nodes[$i]->innertext = "\n";
                    }
                } elseif ($node->tag === 'script' && !$node->hasAttribute('src')) {
                    // Remember the script contents
                    $scripts[$RiotTag_tag][] = [
                        'attributes' => $node->attr,
                        'text' => (string) $node->text()
                    ];

                    unset($RiotTag->nodes[$i]);
                } elseif ($node->tag === 'style') {
                    $styles[$RiotTag_tag][] = [
                        'attributes' => $node->attr,
                        'text' => (string) $node->text()
                    ];

                    unset($RiotTag->nodes[$i]);
                }
            }

            // Reverse order of extracted styles/scripts
            if (isset($styles[$RiotTag_tag])) {
                $styles[$RiotTag_tag] = array_reverse($styles[$RiotTag_tag]);
            }
            if (isset($scripts[$RiotTag_tag])) {
                $scripts[$RiotTag_tag] = array_reverse($scripts[$RiotTag_tag]);
            }

            echo "  (".count($RiotTag->nodes)."):\n";

            foreach ($RiotTag->nodes as $RiotTagChild) {
                if (empty($RiotTagChild)) {
                    continue;
                }

                echo "    {$RiotTagChild->tag} ={$RiotTagChild->nodetype}\n";
            }

            $this->transformExpressions($RiotTag);

            $riot_tags[$RiotTag_tag] = $this->unmaskTextContentTag((string) $RiotTag);
        }

        return [
            // 'mixins' => $mixins,
            'styles' => $styles,
            'scripts' => /*$scripts*/ [],
            'tags' => $tags,
            'riot_tags' => $riot_tags
        ];
    }

    /**
     * Compiles array of files into destination directory
     *
     * @param array $in Input files
     * @param array $out Output directory
     * return void;
     *
     */
    public function compile(array $in = [], $out)
    {
        ob_start();

        if (!realpath($out) || !is_dir($out)) {
            throw new \Exception('Second argument must be a valid path to a directory');
        }

        foreach ($in as $path) {
            if (is_readable($path)) {
                $components = $this->transform(file_get_contents($path));

                foreach ($components['riot_tags'] as $riot_tag_name => $riot_tag_html) {
                    // Put PHP with small indent fix
                    file_put_contents($out.'/'.$riot_tag_name.'.php', preg_replace('|^( +)\n(<\?php.+?\?>\n)|ms', "$2$1", $riot_tag_html));
                }

                foreach ($components['tags'] as $riot_tag_name => $riot_tag_html) {
                    file_put_contents($out.'/'.$riot_tag_name.'.tag', $riot_tag_html);
                }

                foreach ($components['styles'] as $riot_tag_name => $riot_tag_texts) {
                    foreach ($riot_tag_texts as $riot_tag_text) {
                        if (!isset($riot_tag_text['attributes'])) {
                            $riot_tag_text['attributes'] = [];
                        }

                        $attributes = array_merge([
                            'scoped' => false,
                            'type' => 'text/css'
                        ], $riot_tag_text['attributes']);

                        $ext = explode('/', $attributes['type']);
                        $ext = array_pop($ext);

                        if (!$ext) {
                            $ext = 'css';
                        }

                        $riot_tag_text['text'] = preg_replace('|^\s+|m', '', trim($riot_tag_text['text']));

                        if ($attributes['scoped']) {
                            $riot_tag_text['text'] = array_filter(explode('}', $riot_tag_text['text']));
                            $riot_tag_text['text'] = str_replace(':scope', '', "${riot_tag_name} ".implode("}\n${riot_tag_name} ", $riot_tag_text['text'])."}\n");
                        }

                        file_put_contents($out.'/'.$riot_tag_name.'.'.$ext, $riot_tag_text['text']);
                    }
                }

                foreach ($components['scripts'] as $riot_tag_name => $riot_tag_texts) {
                    foreach ($riot_tag_texts as $riot_tag_text) {
                        if (!isset($riot_tag_text['attributes'])) {
                            $riot_tag_text['attributes'] = [];
                        }

                        $attributes = array_merge([
                            'type' => 'text/javascript'
                        ], $riot_tag_text['attributes']);

                        $ext = explode('/', $attributes['type']);
                        $ext = array_pop($ext);

                        if (!$ext || $ext === 'javascript') {
                            $ext = 'js';
                        }

                        file_put_contents($out.'/'.$riot_tag_name.'.'.$ext, $riot_tag_text['text']);
                    }
                }
            }
        }

        ob_end_clean();
    }

    /**
     * Transforms JavaScript variable into PHP
     *
     * Warning: very simple transformation
     *
     * @param string $s Javascript variable name
     * @return string PHP variable counterpart
     *
     */
    protected function phpifyVariable($s)
    {
        // Number || Not string
        if (is_numeric($s) || !is_string($s)) {
            return $s;
        }

        // Other than \w
        if (!preg_match('|\w+|', $s, $m)) {
            return $s;
        }

        $s = trim($s);

        if (strlen($s) === strlen(trim($s, '()"\''))) {
            if (preg_match('|(.+)\.length$|', $s, $m)) {
                return 'strlen('. $this->phpifyVariable($m[1]) .')';
            }

            if (strstr($s, '.')) {
                $s = explode('.', $s);
                $s = array_shift($s)."['".implode("']['", $s)."']";
            }

            return '$'.$s;
        }

        return $s;
    }

    /**
     * Transforms JavaScript expression into PHP
     *
     */
    public function phpifyJsExpression($m)
    {
        if(strstr($m, '||')) {
            $m = explode(' || ', $m);

            foreach ($m as &$mm) {
                $mm = $this->phpifyJsExpression($mm);
            }

            return '('.strtr(implode(' || ', $m), ['%space%' => ' ']).')';
        }

        if(strstr($m, '&&')) {
            $m = explode('&&', $m);

            foreach ($m as &$mm) {
                $mm = $this->phpifyJsExpression($mm);
            }

            return '('.strtr(implode(' && ', $m), ['%space%' => ' ']).')';
        }

        $m = trim($m);

        $m = preg_replace('|\+(\s*[\w\'"])|', '.$1', $m);

        if (preg_match('|^[\'"].+[\'"]$|', $m, $matches)) {
            return str_replace(' ', '%space%', $m);
        }

        $m = preg_replace('|\bnew \b|', 'new%space%', $m);

        // Foreach
        if (strstr($m, ' in ')) {
            $m = explode(' in ', $m);

            if (strstr($m[0], ',')) {
                $m[0] = explode (',', $m[0]);
                $m[0] = $this->phpifyVariable($m[0][1]).' => '.$this->phpifyVariable($m[0][0]);
            } else {
                $m[0] = $this->phpifyVariable($m[0]);
            }

            $m = $this->phpifyVariable($m[1]).' as '.$m[0];
        } elseif (!strstr($m, ' ')) {
            $m = $this->phpifyVariable($m);
        } else {
            $m = explode(' ', $m);

            foreach ($m as &$mm) {
                $mm = $this->phpifyVariable($mm);
            }

            $m = '('.implode(' ', $m).')';
        }

        $m = strtr($m, ['%space%' => ' ']);

        return $m;
    }

    /**
     * Transforms Riot expression into PHP
     *
     * Warning: very simple transformation
     *
     * @param string $s Javascript variable name
     * @param string $ctrl Control expression
     * @return string PHP expression counterpart
     *
     */
    public function phpifyExpression($e, $ctrl = null)
    {
        $e = preg_replace_callback('/'.preg_quote($this->settings['brackets'][0]).'(.+?)'.preg_quote($this->settings['brackets'][1]).'/', function($m) use ($ctrl) {
            $m = str_replace("\t", ' ', trim($m[1]));

            return $ctrl ? $this->phpifyJsExpression($m) : '<?='.$this->phpifyJsExpression($m).'?>';
        }, $e);

        return $e;
    }

    /**
     * Helper function to fix 'style*' and 'script*' custom tags
     *
     * @param string $tag Original tag
     * @return string Transformed tag
     *
     */
    protected function maskTextContentTag($tag)
    {
        return strtr($tag, [
            '<style-' => '<xyz1234567890-style-',
            '</style-' => '<xyz1234567890-style-',
            '<script-' => '<xyz1234567890-script-',
            '</script-' => '<xyz1234567890-script-'
        ]);
    }

    /**
     * Helper function to fix 'style*' and 'script*' custom tags
     *
     * @param string $tag Transformed tag
     * @param string Original tag
     *
     */
    protected function unmaskTextContentTag($tag)
    {
        return strtr($tag, [
            '<xyz1234567890-style-' => '<style-',
            '<xyz1234567890-style-' => '</style-',
            '<xyz1234567890-script-' => '<script-',
            '<xyz1234567890-script-' => '</script-'
        ]);
    }
}
