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
bin/font-calibration [--repeat=20] [--per-page=40]
```
prints one AOML popup link per page, made with `AomlExporter`. Every line in a popup is `|` +
one character repeated 20 times + `|`, followed by its code point:

```
|AAAAAAAAAAAAAAAAAAAA|   U+0041
```

The kerning pages compare pairs with the same letters unpaired:

```
|AVAVAVAVAVAVAVAVAVAV|   AV paired
|AAAAAAAAAAVVVVVVVVVV|   AV unpaired
```

The last page has a reference line, and the last message is the same line as plain chat text.

## In the game
The easiest way to get the popups into the game are client scripts:

```
bin/font-calibration --script=DIR --tell=YourCharacter
```

writes `DIR/aomcal1`, `DIR/aomcal2`, … (16 characters per page, one line each, `/tell` to
yourself, in the client's code page; characters it cannot encode become `&#…;`).

1. Copy the files into the `scripts` folder of the client.
2. Run `/aomcal1`, `/aomcal2`, … in the game; each sends one popup to yourself.
3. Open each popup and make a screenshot (PNG, 100 % UI scale, don't resize the window).
   The last script sends the reference line as plain text: screenshot it in the chat, too.

## Evaluating
For every line: `width = (x of the right bar − x of the left bar − width of "|") / 20`.
If the paired kerning lines are shorter than the unpaired ones, the client kerns, and the
metric needs a pair table besides the widths. `TextMetric::width()` measures whole strings,
so a kerning metric fits without changing the interface.
