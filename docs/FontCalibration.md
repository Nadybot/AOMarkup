# Measuring the font of the AO client

`AoFontMetric` holds one width per character. It was measured by hand first; on 2026-11-27 it
was checked with screenshots of popups made with `bin/font-calibration` (not kept in the
repository). Results:

- Every character has a whole number of pixels. 139 of the 143 characters in the table were
  confirmed exactly; the other four were not measurable (see below), none was wrong.
- **No kerning:** `AVAV…` is exactly as wide as `AAAA…VVVV`, and so for all pairs tested.
- `|` is 6 px; 51 more Latin-1 characters were measured and added.
- **The client shows Latin-1 only.** Scripts have to be sent in Latin-1, and every other
  character (`│`, `•`, `€`, `„`, `“`, `–`, …) shows as `¿`. The slider therefore uses `|` as its
  marker in AOML and BBML.
- `%` came out as 10 instead of 20 characters: scripts treat `%` specially. Its width (14 px)
  is still right.
- Popups do **not** use the font size of the chat: a screenshot of the chat matched Arial at
  about 14 px (space 4 px), the popups match the table (space 5 px).

## Not measured, on purpose
- The chat: we only align text in popups.
- Characters outside Latin-1: the client cannot show them. The exporters write UTF-8;
  converting to the client's encoding is the job of the transport, not of this library.

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
