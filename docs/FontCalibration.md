# Measuring the font of the AO client

`AoFontMetric` holds one width per character. The current table was measured by hand and
is about 7–10 % too wide. A screenshot of the chat suggests that the client sets **Arial at
about 14 px** (space 4 px, digits 8 px) but draws the glyphs a little smaller. This page
describes how to measure the real widths in the game.

## Open questions
1. The width of every character (to 1/20 px).
2. Does the client use kerning? (`AV` is about 1 px narrower than `A` + `V` in Arial.)
3. Do popups use the same font size as the chat?

## Generating the test popups
```
bin/font-calibration [repeat]
```
prints seven AOML popup links (one per line), made with `AomlExporter`. Every line in a
popup is `|` + one character repeated 20 times + `|`, followed by its code point:

```
|AAAAAAAAAAAAAAAAAAAA|   U+0041
```

The last page compares kerning pairs with the same letters unpaired:

```
|AVAVAVAVAVAVAVAVAVAV|   AV paired
|AAAAAAAAAAVVVVVVVVVV|   AV unpaired
```

## In the game
1. Send the links to yourself (e.g. through the bot), open each popup and make a
   screenshot at 100 % UI scale, without resizing the window.
2. Also send one calibration line as plain chat text, to compare chat and popups.

## Evaluating
For every line: `width = (x of the right bar − x of the left bar − width of "|") / 20`.
If the paired kerning lines are shorter than the unpaired ones, the client kerns, and the
metric needs a pair table besides the widths. `TextMetric::width()` measures whole strings,
so a kerning metric fits without changing the interface.
