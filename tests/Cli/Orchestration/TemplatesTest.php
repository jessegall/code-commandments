<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Orchestration\Profile;
use JesseGall\CodeCommandments\Cli\Orchestration\Templates;
use PHPUnit\Framework\TestCase;

/**
 * A scaffold asks a question and leaves a blank; a template shows an answer somebody already found worth
 * keeping. Discovered from the folder rather than listed, so shipping one is adding a file.
 */
final class TemplatesTest extends TestCase
{
    private function templates(): Templates
    {
        return Templates::shipped();
    }

    public function test_the_shipped_templates_are_found(): void
    {
        $this->assertContains('roles/secretary', $this->templates()->all());
    }

    public function test_a_template_reads_out(): void
    {
        $body = $this->templates()->read('roles/secretary');

        $this->assertTrue($body->isSome());
        $this->assertStringContainsString('QUOTE, DO NOT SUMMARISE', $body->unwrap());
    }

    public function test_a_template_nobody_ships_is_absent(): void
    {
        $this->assertTrue($this->templates()->read('roles/nothing')->isNone());
        $this->assertTrue($this->templates()->read('nonsense')->isNone());
    }

    /**
     * A name is a path into the shipped folder and nothing else. Reaching outside it with `..` would let
     * a caller read any file on the machine through a command that exists to print a template.
     */
    public function test_a_name_cannot_climb_out_of_the_template_folder(): void
    {
        $this->assertTrue($this->templates()->read('roles/../../composer')->isNone());
        $this->assertTrue($this->templates()->read('../composer')->isNone());
    }

    public function test_about_is_the_first_line_of_prose(): void
    {
        $about = $this->templates()->about('roles/secretary');

        $this->assertStringContainsString('Files what workers report', $about);
        $this->assertStringNotContainsString('#', $about, 'the heading is not the description');
        $this->assertStringNotContainsString('type:', $about);
    }

    /**
     * A template's own folder decides where it lands, so nothing has to be told twice.
     */
    public function test_a_role_lands_under_roles_and_a_document_at_the_top(): void
    {
        $profile = new Profile('editor', '/tmp/profiles/editor');

        $this->assertSame('/tmp/profiles/editor/roles/secretary.md', $this->templates()->homeIn($profile, 'roles/secretary'));
        $this->assertSame('/tmp/profiles/editor/routine.md', $this->templates()->homeIn($profile, 'documents/routine'));
    }
}
