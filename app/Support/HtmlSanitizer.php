<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Strips rich-text HTML down to the tags/attributes the app's Flux editor
 * toolbars can actually produce. Needed because form input arrives as a plain
 * string over the wire — nothing guarantees it was produced by the editor's
 * own sanitized output, so it must be cleaned before being stored and later
 * rendered unescaped.
 */
class HtmlSanitizer
{
    private const ALLOWED_TAGS = ['p', 'br', 'h1', 'h2', 'h3', 'strong', 'em', 's', 'u', 'mark', 'ul', 'ol', 'li', 'blockquote', 'a'];

    private const ALLOWED_TEXT_ALIGN = ['left', 'center', 'right', 'justify'];

    /** Tags whose content is never meaningful text and should be dropped entirely, not unwrapped. */
    private const DROP_WITH_CONTENTS = ['script', 'style'];

    public static function clean(string $html): string
    {
        $document = new DOMDocument;

        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div>'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();

        $root = $document->getElementsByTagName('div')->item(0);

        if (! $root) {
            return '';
        }

        self::sanitizeChildren($root, $document);

        $html = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $html .= $document->saveHTML($child);
        }

        return trim($html);
    }

    private static function sanitizeChildren(DOMNode $node, DOMDocument $document): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                $node->removeChild($child);

                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENTS, true)) {
                $node->removeChild($child);

                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                self::sanitizeChildren($child, $document);

                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }

                $node->removeChild($child);

                continue;
            }

            self::sanitizeAttributes($child, $tag);
            self::sanitizeChildren($child, $document);
        }
    }

    private static function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);

            if ($tag === 'a' && $name === 'href') {
                if (! preg_match('/^(https?:|mailto:)/i', trim($attribute->value))) {
                    $element->removeAttribute($attribute->name);
                }

                continue;
            }

            if ($tag === 'a' && in_array($name, ['rel', 'target'], true)) {
                continue;
            }

            if ($name === 'style') {
                $style = self::sanitizeStyle($attribute->value);

                $style === '' ? $element->removeAttribute('style') : $element->setAttribute('style', $style);

                continue;
            }

            $element->removeAttribute($attribute->name);
        }
    }

    private static function sanitizeStyle(string $style): string
    {
        $kept = [];

        foreach (explode(';', $style) as $declaration) {
            [$property, $value] = array_pad(array_map('trim', explode(':', $declaration, 2)), 2, '');

            if (strtolower($property) === 'text-align' && in_array(strtolower($value), self::ALLOWED_TEXT_ALIGN, true)) {
                $kept[] = "text-align: {$value}";
            }
        }

        return implode('; ', $kept);
    }
}
