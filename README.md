# Riot PHP Tags
[Experimental] [Riot.js](https://github.com/riot/riot) templates to PHP transformation

### Supported features

- [x] Quotes are optional: `<foo bar={ baz }>` becomes `<foo bar="{ baz }">`.
- [x] Without the `<script>` tag the JavaScript starts where the last HTML tag ends
- [x] Tag styling (extracted)
- [x] Scoped CSS
- [ ] Nested tags
  - [ ] Options
  - [ ] Yeld
- [ ] Mixins
- [ ] Expressions
  - [x] `{ var }` becomes `<?=$var?>`
  - [x] `{ a() }` becomes `<?=a()?>` (maybe should become `<?=$this['a']()?>` or `<?=$a()?>`)
  - [x] `{ var.value }` becomes `<?=$var['value']?>`
  - [ ] `{ title || 'Untitled' }`
  - [ ] `{ results ? 'ready' : 'loading' }`
  - [ ] `{ new Date() }`
  - [ ] `{ message.length > 140 && 'Message is too long' }`
  - [ ] `{ Math.round(rating) }`
- [ ] Boolean attributes (checked, selected etc..)
  - [ ] `<input checked={ null }>` becomes `<input>`
  - [ ] 44 different boolean attributes
- [ ] Class shorthand:
  - [ ] `class={ completed: done }` becomes `class="completed"` when the value of done is a true value
  - [ ] `<p class={ foo: true, bar: 0, baz: new Date(), zorro: 'a value' }></p>` evaluates to `<p class="foo baz zorro"></p>`
- [ ] Printing brackets
- [x] Customizing curly braces
- [x] Custom tags can be empty, HTML only or JavaScript only
- [x] Conditionals
  - [x] `if={ ... }`
  - [x] `show={ ... }`
  - [x] `hide={ ... }`
- [x] Loops
  - [ ] `each="{ items }"`
  - [x] `each="{ name, i in arr }"`
  - [x] `each="{ name, value in obj }"
  - [ ] `no-reorder` attr support
  - [ ] `parent.` context
  - [ ] Pass the item as an option to the looped tag with `data={ ... }`
- [ ] VIRTUAL tag
- [ ] HTML elements as tags
  - [ ] `<ul riot-tag="my-tag"></ul>`
- [ ] Server-side rendering

### Notes

- Pre-processors should be handled by a separate tool
- Complex expressions would require tokenization
- `{ title || 'Undefined' }` or `{ title && 'Defined' }` does not behave as in JavaScript
