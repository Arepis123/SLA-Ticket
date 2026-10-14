<?php

namespace Tests\Feature;

use App\Support\HtmlSanitizer;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_it_strips_scripts_event_handlers_and_unsafe_links(): void
    {
        $dirty = '<p onclick="steal()">Hello <strong onmouseover="alert(1)">world</strong></p>'
            .'<script>alert(1)</script>'
            .'<a href="javascript:alert(1)">bad link</a>'
            .'<img src="x" onerror="alert(1)">';

        $clean = HtmlSanitizer::clean($dirty);

        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onmouseover', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('<img', $clean);
        $this->assertSame('<p>Hello <strong>world</strong></p><a>bad link</a>', $clean);
    }

    public function test_it_keeps_formatting_the_default_editor_toolbar_can_produce(): void
    {
        $html = '<h2>Heading</h2><p style="text-align: center">Centered <em>text</em></p>'
            .'<ul><li>one</li><li>two</li></ul><blockquote>Quoted</blockquote>'
            .'<a href="https://example.com" target="_blank" rel="noopener">link</a>';

        $this->assertSame($html, HtmlSanitizer::clean($html));
    }

    public function test_it_unwraps_disallowed_tags_but_keeps_their_text(): void
    {
        $this->assertSame('<p>Hello</p>', HtmlSanitizer::clean('<div><p>Hello</p></div>'));
        $this->assertSame('plain text', HtmlSanitizer::clean('<span class="x">plain text</span>'));
    }

    public function test_it_only_keeps_text_align_from_style_attributes(): void
    {
        $clean = HtmlSanitizer::clean('<p style="text-align: center; color: red; background: url(evil)">x</p>');

        $this->assertSame('<p style="text-align: center">x</p>', $clean);
    }
}
