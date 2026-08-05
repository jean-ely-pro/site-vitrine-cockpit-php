<?php

declare(strict_types=1);

namespace Media;

/**
 * Makes the description of an image compulsory.
 *
 * An image without a description is unreadable to anyone using a screen
 * reader, and shows nothing at all when it fails to load. The rule is simple:
 * wherever the content model puts an « alt » field next to an image, filling
 * the image means filling the description.
 */
final class AltText
{
    /**
     * Returns the label of the first image left undescribed, or null.
     *
     * Sets are walked too: a page keeps its images inside blocks.
     *
     * @param array<int, array<string, mixed>> $fields
     * @param array<string, mixed> $data
     */
    public static function missingIn(array $fields, array $data): ?string
    {
        $names = array_column($fields, 'name');

        foreach ($fields as $field) {

            $name = $field['name'] ?? null;
            $type = $field['type'] ?? null;

            if (!is_string($name)) {
                continue;
            }

            if ($type === 'asset' && !empty($data[$name]) && in_array('alt', $names, true)) {

                if (trim((string) ($data['alt'] ?? '')) === '') {
                    return (string) ($field['label'] ?? $name);
                }

                continue;
            }

            if ($type === 'set' && is_array($field['opts']['fields'] ?? null) && is_array($data[$name] ?? null)) {

                $inner = $field['opts']['fields'];
                $entries = empty($field['multiple']) ? [$data[$name]] : $data[$name];

                foreach ($entries as $entry) {

                    if (!is_array($entry)) {
                        continue;
                    }

                    $missing = self::missingIn($inner, $entry);

                    if ($missing !== null) {
                        return $missing;
                    }
                }
            }
        }

        return null;
    }
}
