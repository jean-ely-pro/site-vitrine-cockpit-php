<?php

declare(strict_types=1);

namespace EditorGuards;

/**
 * Keeps heading levels inside what the page structure allows.
 *
 * A page carries exactly one level-one heading — its title. A level-one
 * heading typed in the body would give the page two, and search engines and
 * screen readers both read that as two documents in one.
 *
 * Levels four and below are folded into three: a text with such depth on a
 * brochure site is almost always pasted from elsewhere, with its own outline.
 */
final class Headings
{
    /**
     * Rewrites every rich text value of an item, following the model.
     *
     * Sets are walked too: a page keeps its text inside blocks. Values are
     * returned rather than modified in place — passing references through
     * Cockpit's module layer does not survive the call.
     *
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function inFields(array $fields, array $data): array
    {
        foreach ($fields as $field) {

            $name = $field['name'] ?? null;
            $type = $field['type'] ?? null;

            if (!is_string($name) || !isset($data[$name])) {
                continue;
            }

            $multiple = !empty($field['multiple']);
            $value = $data[$name];

            if ($type === 'wysiwyg') {

                if ($multiple && is_array($value)) {
                    $data[$name] = array_map(
                        static fn (mixed $one): mixed => is_string($one) ? self::normalise($one) : $one,
                        $value,
                    );
                } elseif (is_string($value)) {
                    $data[$name] = self::normalise($value);
                }

                continue;
            }

            if ($type === 'set' && is_array($field['opts']['fields'] ?? null) && is_array($value)) {

                $inner = $field['opts']['fields'];

                $data[$name] = $multiple
                    ? array_map(
                        static fn (mixed $entry): mixed => is_array($entry) ? self::inFields($inner, $entry) : $entry,
                        $value,
                    )
                    : self::inFields($inner, $value);
            }
        }

        return $data;
    }

    public static function normalise(string $html): string
    {
        if ($html === '' || stripos($html, '<h') === false) {
            return $html;
        }

        // <h1> becomes <h2>, <h4>…<h6> become <h3>. Attributes are kept.
        return (string) preg_replace_callback(
            '#<(/?)h([1-6])(\s[^>]*)?>#i',
            static function (array $match): string {
                $level = (int) $match[2];
                $target = match (true) {
                    $level === 1 => 2,
                    $level >= 4 => 3,
                    default => $level,
                };

                return "<{$match[1]}h{$target}".($match[3] ?? '').'>';
            },
            $html,
        );
    }
}
