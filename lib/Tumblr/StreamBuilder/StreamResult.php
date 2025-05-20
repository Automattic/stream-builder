<?php
/**
 * The StreamBuilder framework.
 * Copyright 2023 Automattic, Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace Tumblr\StreamBuilder;

use Tumblr\StreamBuilder\Exceptions\TypeMismatchException;
use Tumblr\StreamBuilder\StreamCursors\StreamCursor;
use Tumblr\StreamBuilder\StreamElements\DerivedStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\Streams\Stream;

/**
 * Returned from calls to enumerate streams. A possibly-exhaustive sequence of elements.
 */
class StreamResult extends Templatable
{
    /**
     * If true, this stream has been exhausted. There are no more results that can be paginated next.
     * If false, pagination is still possible.
     * This is how it works, in theory. Since we don't validate, it's only an indication.
     * @var bool
     */
    private bool $is_exhaustive;

    /**
     * @var StreamElement[]
     */
    private array $elements;

    /**
     * @param bool $is_exhaustive True iff this enumeration was exhaustive. See $is_exhaustive.
     * @param array $elements Array[StreamElement] containing the results of enumeration.
     * @throws TypeMismatchException If some member of $elements is not a StreamElement.
     */
    public function __construct(bool $is_exhaustive, array $elements)
    {
        foreach ($elements as $e) {
            if (!($e instanceof StreamElement)) {
                throw new TypeMismatchException(StreamElement::class, $e);
            }
        }
        $this->is_exhaustive = $is_exhaustive || empty($elements);
        $this->elements = $elements;
    }

    /**
     * Get the contained elements.
     * @return StreamElement[] Array containing the results of enumeration.
     */
    public function get_elements(): array
    {
        return $this->elements;
    }

    /**
     * Get the contained original elements.
     * @return StreamElement[]
     */
    public function get_original_elements(): array
    {
        return array_map(function (StreamElement $element) {
            return $element->get_original_element();
        }, $this->elements);
    }

    /**
     * To get a certain element at position.
     * @param int $index The element position.
     * @return StreamElement|null
     */
    public function get_element_at_index(int $index)
    {
        return $this->elements[$index] ?? null;
    }

    /**
     * @return bool True iff this enumeration was exhaustive. See $is_exhaustive.
     */
    public function is_exhaustive(): bool
    {
        return $this->is_exhaustive;
    }

    /**
     * @return int
     */
    public function get_size(): int
    {
        return count($this->elements);
    }

    /**
     * @param int|null $until Combine elements' cursors until the given index or until the end if null.
     * @return StreamCursor|null
     */
    public function get_combined_cursor(?int $until = null)
    {
        $combined = null;
        foreach ($this->elements as $i => $e) {
            if ($i === $until) {
                break;
            }
            /** @var StreamElement $e */
            if ($cur = $e->get_cursor()) {
                $combined = $cur->combine_with($combined);
            }
        }
        return $combined;
    }

    /**
     * Derive all elements, providing a replacement stream. If you require more advanced derivation,
     * such as modifying cursors, you'll need to do the derivation manually.
     * By default, it will use original element's cursor.
     *
     * @param Stream $stream The stream to provide during derivation.
     * @return StreamResult A new StreamResult containing derived elements.
     */
    public function derive_all(Stream $stream): self
    {
        $new_elems = [];
        foreach ($this->elements as $e) {
            $new_elems[] = new DerivedStreamElement($e, $stream->get_identity(), $e->get_cursor());
        }
        return new StreamResult($this->is_exhaustive, $new_elems);
    }

    /**
     * Provide an efficient bulk fetch method to inflate all stream elements with real post/blog model after stream enumeration.
     * Call this method as late as possible, we always prefer using light weighted id based elements for stream mixing.
     * For those elements have already been inflated, this call will not fetch them again.
     * @return void
     */
    public function bulk_fetch()
    {
        StreamElement::pre_fetch_all($this->elements);
    }

    /**
     * Prepend elements to this StreamResult.
     * @param array $prepend_elements The elements to prepend, in order.
     * @param StreamResult $result The StreamResult into which to prepend.
     * @return StreamResult A new result with the elements prepended.
     */
    public static function prepend(array $prepend_elements, StreamResult $result): self
    {
        return new StreamResult($result->is_exhaustive, array_merge($prepend_elements, $result->elements));
    }

    /**
     * To create an empty stream result, with is_exhaustive flag true.
     * @return StreamResult
     */
    public static function create_empty_result(): self
    {
        return new self(true, []);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function to_template(): array
    {
        return [
            '_type' => get_class($this),
            'is_exhaustive' => $this->is_exhaustive,
            'elements' => array_map(function (StreamElement $element) {
                return $element->to_template();
            }, $this->elements),
        ];
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public static function from_template(StreamContext $context): self
    {
        $element_templates = $context->get_required_property('elements');
        $elements = [];
        foreach ($element_templates as $i => $element_template) {
            $elements[] = StreamSerializer::from_template($context->derive($element_template, sprintf('elements/%d', $i)));
        }
        return new self(
            $context->get_required_property('is_exhaustive'),
            $elements
        );
    }

    /**
     * Get the string representation of the current stream result.
     * @return string
     */
    public function __toString(): string
    {
        return sprintf(
            '%s(%s)',
            Helpers::get_unqualified_class_name($this),
            implode(',', $this->elements)
        );
    }
}
