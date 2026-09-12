<?php
/*
|--------------------------------------------------------------------------
| HTML Output Normalizer
|--------------------------------------------------------------------------
| Every rendered page is a mix of independently-indented partials (header,
| footer, card/section includes, etc.), so the raw HTML that reaches the
| browser ends up with inconsistent indentation and blank lines. It also
| relies on short-echo PHP tags all over the templates, and PHP's parser
| silently eats a single newline right after a tag's closing delimiter -
| so a value and the tag that follows it on the next source line often
| end up concatenated with that next line's leading indentation stuck in
| between as a stray run of spaces (e.g. "Dr. Name          </a>").
|
| <head> (meta/link/title/script tags, not layout) only gets the basic
| cleanup: each line left-aligned, blank lines dropped, everything else
| left exactly as one tag per line.
|
| <body> gets that same basic cleanup, but its content is then collapsed
| further: a line break happens only right before a <div ...> tag that is
| NOT already nested inside another currently-open div - i.e. one line
| per top-level div (a whole section, nested divs and all). A <div>
| opened while already inside another div never breaks the line on its
| own; everything it and its ancestor div directly contain (headings,
| text, a multi-line tag's own attributes, spans, forms, inputs, buttons,
| deeper nested divs, ...) joins onto that one line. A closing </div>
| never starts a new line either way; it stays glued to whatever precedes
| it, so it never sits alone as an orphan line.
|
| That "top-level only" rule can't tell a repeating list item's own root
| element (which should always break, however deeply it's nested - e.g.
| one line per doctor card) apart from an ordinary nested div that's just
| one part of its parent's content (which should stay merged) - both look
| identical from nesting depth alone. Templates that loop over cards mark
| the boundary explicitly by calling front_line_break() right before each
| item's root element (a <div>, an <article>, anything); that call
| outputs a private marker which unconditionally forces a line break at
| that exact point and then removes itself from the final output. <div>
| depth tracking continues normally from there, so anything the marked
| element itself nests still only breaks per the usual div rule.
|
| <pre>, <textarea>, <script> and <style> blocks are protected first,
| since whitespace inside those can be meaningful - except JSON-LD script
| blocks, which are safe to flatten too since JSON syntax is
| whitespace-insignificant.
|
| Dependency-free on purpose, so the public site, user panel and
| prescription module can each register it as their own ob_start()
| callback (in their own header.php) without pulling in unrelated code.
| Only pages that include a header.php buffer through this, so binary or
| JSON endpoints that merely require a shared functions file (e.g.
| doctor/generate-doctor-card.php, ajax/*.php) are never touched.
|--------------------------------------------------------------------------
*/
// trim()'s default charlist strips NUL bytes, so a \x00-based marker
// would get silently corrupted by the trim() calls used throughout this
// file. This charset can't occur naturally in HTML markup, so it
// survives trim() intact and stays a safe, unique marker.
if (!defined('FRONT_LINE_BREAK_MARKER')) {
    define('FRONT_LINE_BREAK_MARKER', '@@@FRONT_BREAK@@@');
}

// Call this right before echoing/including a repeating list item's root
// element (e.g. right before a doctor/hospital card partial, inside the
// foreach loop) so that item always starts its own line, regardless of
// how deeply its root <div> is actually nested.
if (!function_exists('front_line_break')) {
    function front_line_break(): void
    {
        echo FRONT_LINE_BREAK_MARKER;
    }
}

if (!function_exists('front_normalize_html_output')) {
    function front_normalize_html_output(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $placeholders = [];

        $protected = preg_replace_callback(
            '#<(pre|textarea|script|style)\b[^>]*>.*?</\1\s*>#is',
            function (array $match) use (&$placeholders): string {
                if (preg_match('#^<script\b[^>]*type=["\']application/ld\+json["\']#i', $match[0])) {
                    return $match[0];
                }

                // trim()'s default charlist strips NUL bytes, so a \x00-based
                // marker gets silently corrupted by the trim() call below.
                // This charset can't occur naturally in HTML markup, so it
                // survives trim() intact and stays a safe, unique marker.
                $key = '@@@FRONT_KEEP_' . count($placeholders) . '@@@';
                $placeholders[$key] = $match[0];

                return $key;
            },
            $html
        );

        if ($protected === null) {
            return $html;
        }

        $headEnd = stripos($protected, '</head>');

        if ($headEnd !== false) {
            $headEnd += strlen('</head>');
            $head = front_html_basic_normalize(substr($protected, 0, $headEnd));
            $body = front_html_compact_normalize(substr($protected, $headEnd));
            $result = $head . "\n" . $body;
        } else {
            $result = front_html_compact_normalize($protected);
        }

        return $placeholders !== [] ? strtr($result, $placeholders) : $result;
    }
}

// Left-aligns every line and drops blank lines, keeping one tag per line
// otherwise untouched. Used for <head>.
if (!function_exists('front_html_basic_normalize')) {
    function front_html_basic_normalize(string $html): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $html);
        $clean = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed !== '') {
                $clean[] = $trimmed;
            }
        }

        return implode("\n", $clean);
    }
}

// Collapses a fragment so a line break only happens right after an
// opening <div ...> tag. Used for <body>.
if (!function_exists('front_html_compact_normalize')) {
    function front_html_compact_normalize(string $html): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $html);
        $clean = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed !== '') {
                $clean[] = $trimmed;
            }
        }

        // Join with a single space (never nothing) so text split across
        // lines - e.g. by the newline-eating described in the file
        // docblock, or a tag's own attributes spanning several lines -
        // never gets glued together without a separator.
        $result = implode(' ', $clean);

        // That newline-eating can also leave a run of spaces in the middle
        // of what is now one line (the next line's leading indentation,
        // stuck directly after a value with no newline between them, e.g.
        // "Dr. Name          </a>"). Collapse any such run down to one
        // space first...
        $result = preg_replace('/[ \t]{2,}/', ' ', $result);

        // ...then drop that space wherever it sits purely between two
        // tags (">  <" -> "><"), which is always safe since no visible
        // content depends on it.
        $result = preg_replace('/>\s+</', '><', $result);

        // A front_line_break() marker forces an unconditional line break
        // wherever it was echoed, regardless of what tag follows it (a
        // <div>, an <article>, anything) - then removes itself. This runs
        // as its own step, independent of the <div> depth tracking below,
        // so it works no matter what a repeating list item's root element
        // actually is.
        $markerQuoted = preg_quote(FRONT_LINE_BREAK_MARKER, '/');
        $result = preg_replace('/' . $markerQuoted . '\s*/', "\n", $result);

        // Break the line right before a <div ...> tag only when it is NOT
        // already nested inside another open div - so one whole top-level
        // div (a section, with every div nested inside it) stays on one
        // line, and only the next sibling top-level div starts fresh.
        // </div> never gets a break either way, so it always stays glued
        // to whatever precedes it.
        $depth = 0;
        $result = preg_replace_callback(
            '/<div\b[^>]*>|<\/div>/i',
            function (array $match) use (&$depth): string {
                if ($match[0][1] === '/') {
                    // Closing </div>: step back out one level, no break.
                    $depth = max(0, $depth - 1);

                    return $match[0];
                }

                // Opening <div ...>: break only if this is a top-level div.
                $isTopLevel = $depth === 0;
                $depth++;

                return $isTopLevel ? "\n" . $match[0] : $match[0];
            },
            $result
        );

        // Collapse a marker-break landing right next to a div-break (both
        // wanted a newline at the same spot) down to one, and drop any
        // leading newline left at the very start of the fragment.
        $result = preg_replace('/\n+/', "\n", $result);
        $result = ltrim($result, "\n");

        return $result;
    }
}
