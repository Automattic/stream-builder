# StreamBuilder: A beginner's guide

**Under construction, feedback welcome!**

### Preface

This documentation is a detailed example of how to implement features with the StreamBuilder framework. We will use a concrete case to go through different components and demo the current best practice with this framework. We will not introduce every method/field of components as most of them are already documented with inline code comments, you can check those out with `Code` links.

We also have more detailed explanation of the functionalities for individual high-level components in the [Appendix](#appendix) section.

### Table of contents

- [The journey begins](#the-journey-begins)
  - [What we will implement in this doc](#what-we-will-implement-in-this-doc)
  - [Integrating StreamBuilder with your codebase](#integrating-streambuilder-with-your-codebase)
  - [Start with a very basic Stream](#start-with-a-very-basic-stream)
  - [Support pagination](#support-pagination)
  - [Put components into a predefined template](#put-components-into-a-predefined-template)
  - [Logging & Ticks](#logging--ticks)
  - [Let's implement more advanced options](#lets-implement-more-advanced-options)
    - [What if we need to filter topics at runtime?](#what-if-we-need-to-filter-topics-at-runtime)
    - [What if we want to combine different versions of trending topics?](#what-if-we-want-to-combine-different-versions-of-trending-topics)
    - [What if we want to rank the topics?](#what-if-we-want-to-rank-the-topics)
    - [What if we want to inject some manual topic?](#what-if-we-want-to-inject-some-manual-topic)
	- [What if we want to cache a stream?](#what-if-we-want-to-cache-a-stream)

- [Appendix](#appendix)
  - [Template Files](TemplateFiles.md)
  - [Stream](#stream)
  - [Templatable](#templatable)
  - [Identifiable](#identifiable)
  - [StreamContext](#streamcontext)
  - [StreamResult](#streamresult)
  - [StreamElement](#streamelement)
  - [StreamCursor](#streamcursor)
  - [StreamCombiner](#streamcombiner)
  - [StreamInjector](#streaminjector)
  - [InjectionAllocator](#injectionallocator)
  - [StreamTracer](#streamtracer)
  - [StreamFilter](#streamfilter)
  - [StreamRanker](#streamranker)
  - [CappedPostRanker](#cappedpostranker)
  - [Fencepost](#fencepost)

## The journey begins

StreamBuilder expects PHP 7.4+. Some of the examples in this guide will expect PHP 8.0+.

### What we will implement in this doc

As an example of how to use StreamBuilder, we will implement an endpoint to retrieve trending topics. We will start from the very basic assumption that we already have a data source that can retrieve trending topics via this pseudocode:

```php
(new TrendingSource())->getTrendingPosts(): array
```

Let's also assume the returned array is a simple `string[]` which contains trending topics as strings.

We also have an example demo application in the `example/` folder which implements a lot of what we're going to discuss here -- so check that out! You must clone the Git repository to work with the example, as released archives do not include example code.

### Integrating StreamBuilder with your codebase

Install StreamBuilder by using [Composer](https://getcomposer.org/) and running `composer require automattic/stream-builder:$newest_version` in your project. Then, follow these steps to integrate it with your system.

1. Implement your subclasses of the abstract classes in `Tumblr\StreamBuilder\Interfaces`: `Log`, `Credentials`, `PostStreamElementInterface`, and `User`. Create subbed versions if a particular class is not applicable to your system.
2. Initialize StreamBuilder by running `StreamBuilder::init($dependency_bag)`. The `$dependency_bag` is where you declare your implementations. Take this example from `DependencyBagTest::retrieveDependencyBag()`:

```php
$dependency_bag = new DependencyBag(
    new MockedLog(),
    new TransientCacheProvider(),
    new MockedCredentials(),
    new TestContextProvider()
);

StreamBuilder::init($dependency_bag);
```

For using cursors (more on this later), you'll need to implement `Credentials` and provide all values that `StreamCursorSerializer` uses:

- `DASHBOARD_STREAM_CURSOR_SECRET`
- `SEARCH_STREAM_CURSOR_ENCRYPT_KEY`
- `DASHBOARD_STREAM_CURSOR_ENCRYPT_KEY`
- `SEARCH_STREAM_CURSOR_IV_SALT`
- `DASHBOARD_STREAM_CURSOR_IV_SALT`

These values can be anything you want, and they will be used to encrypt/decrypt cursors. You can use the same value for all of them if you want. (We won't be providing security suggestions in this guide.)

Implementing `ContextProvider` requires the declaration of three methods:

1`getBaseDir()`: the base directory of your working app. This is usually the same value as `__DIR__` called in the root app folder.
2`getContextProvider()`: a list of template directories within your working app. The template directories are where StreamBuilder will look for template YAML files. The template files are configuration files used to render the results of a stream. We will talk about actually implementing the templates later, for now we just need the folder to exist.
3`getConfigDir()`: the directory of a different repository that contains templates outside your working app. Return null if you don't plan to have an external directory for stream templates. **Important: if you declare a config directory, we will look for templates in `${getConfigDir()}/config/stream_templates`**. For example, if you declare `getConfigDir()` as `/user/config` we will scan for templates in `/user/config/config/stream_templates`.

You only need to call `StreamBuilder::init()` once. After that, you can use StreamBuilder anywhere in your code. Calling it more than once will throw an error.

### Start with a very basic Stream

We will need to build an implementation of [Stream](#stream) to enumerate content.

Let's call our implementation `TrendingTopicStream`:

```php
class TrendingTopicStream extends Stream
{
    /**
     * The constructor
     * @param string $identity The identity of the stream.
     */
    public function __construct(string $identity)
    {
        parent::__construct($identity);
    }

    /** @inheritDoc */
    protected function _enumerate(int $count, StreamCursor $cursor = null, StreamTracer $tracer = null, ?EnumerationOptions $option = null): StreamResult
    {
        $topics = (new TrendingSource())->getTrendingPosts();
        $elements = [];
        foreach ($topics as $topic) {
            $elements[] = new TrendingTopicStreamElement($topic, $this->get_identity(), $cursor);
        }
        return new StreamResult(true, $elements);
    }

    /** @inheritDoc */
    public static function from_template(StreamContext $context)
    {
        return new self($context->get_current_identity());
    }
}
```

You'll notice 3 things in the above implementation:

- We implement the required method `_enumerate()` which handles the business logic for fetching our items in the stream.
- After we get `$topics` we wrap them inside another object called `TrendingTopicStreamElement`, the reason for this will be introduced in the [StreamElement](#streamelement) section.
- We implement another required method `from_template`, the reason for this will be introduced in the [Templatable](#templatable) section.

Now let's implement `TrendingTopicStreamElement`, which is a simple wrapper for each topic and can contain their required business logic:

```php
class TrendingTopicStreamElement extends LeafStreamElement
{
    /** @var string The underlying topic, as a string */
    private string $topic;

    /**
     * @param string $topic The topic id
     * @param string $provider_identity The identity
     * @param StreamCursor|null $cursor The cursor
     * @param string|null $element_id An unique id used to trace the entire lifecycle of this element.
     */
    public function __construct(string $topic, string $provider_identity, ?StreamCursor $cursor = null, ?string $element_id = null) {
        parent::__construct($provider_identity, $cursor, $element_id);
        $this->topic = $topic;
    }

    /** @inheritDoc */
    public function get_cache_key()
    {
        return $this->topic;
    }

    /** @inheritDoc */
    protected function to_string(): string
    {
        return "TrendingTopic:$this->topic";
    }

    /** @inheritDoc */
    public static function from_template(StreamContext $context)
    {
        return new self(
            $context->get_required_property('topic'),
            $context->get_optional_property('provider_id', ''),
            $context->deserialize_optional_property('cursor'),
            $context->get_optional_property('element_id', null)
        );
    }

    /** @inheritDoc **/
    public function to_template(): array
    {
        $base = parent::to_template();
        $base['topic'] = $this->topic;
        return $base;
    }
}
```

In this example, the `TrendingTopicStreamElement` is a wrapper around the `topic` string. The methods `from_template` and `to_template` are covered in the [Templatable](#templatable) section.

The method

```php
public function get_cache_key()
```

is worth explaining further. The cache key returned is used to cache the element (used in `CachedStream`) or cache the filter result (used in `CachedStreamFilter`) for faster retrieval in subsequent requests, if a cache is implemented. So the cache key must contain the unique id of the underlying data. (The caching logic itself is implemented by `CacheProvider`, used in `CachedStream` and `CacheStreamFilter`.) Caching is not required, so returning `null` in this method is also acceptable when you know you don't want or need the element cached.

Another method

```php
protected function to_string(): string
```

is mostly used in debugging and tracing, so feel free to return anything you want there as long as it's unique to this instance.

With this simple implementation, we can now already enumerate the source with

```php
$elements = (new TrendingTopicStream('trending'))->enumerate(10)->get_elements();
```

The `$elements` we get is of type `TrendingTopicStreamElement[]`.

### Support pagination

Now we've implemented `TrendingTopicStream` and `TrendingTopicStreamElement`. We're able to enumerate content with the Stream. But how do we support pagination for the Stream? We need a [StreamCursor](#streamcursor) implementation.

Let's circle back to the data source:

```php
(new TrendingSource())->getTrendingPosts();
```

Let's assume it can use `offset` and `limit` to do pagination like a SQL query.

So the data source call becomes:

```php
(new TrendingSource())->getTrendingPosts($offset, $limit);
```

And the cursor implementation will look like:

```php
class TrendingTopicStreamCursor extends StreamCursor
{
    /** @var int The offset in this cursor */
    private int $offset;

    /**
     * TrendingTopicsStreamCursor constructor.
     * @param int $offset Offset
     * @throws \InvalidArgumentException When offset is negative.
     */
    public function __construct(int $offset)
    {
        parent::__construct(Helpers::get_unqualified_class_name($this));
        if ($offset < 0) {
            throw new \InvalidArgumentException("Offset cannot be negative");
        }
        $this->offset = $offset;
    }

    /**
     * @return int The offset.
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /** @inheritDoc */
    protected function _can_combine_with(StreamCursor $other): bool
    {
        return $other instanceof TrendingTopicStreamCursor;
    }

    /** @inheritDoc */
    protected function _combine_with(StreamCursor $other): StreamCursor
    {
        /** @var TrendingTopicStreamCursor $other */
        return $this->getOffset() > $other->getOffset() ? $this : $other;
    }

    /** @inheritDoc */
    protected function to_string(): string
    {
        return sprintf('%s(%d)', Helpers::get_unqualified_class_name($this), $this->getOffset());
    }

    /** @inheritDoc */
    public function to_template(): array
    {
        $base = parent::to_template();
        $base['offset'] = $this->getOffset();
        return $base;
    }

    /** @inheritDoc */
    public static function from_template(StreamContext $context)
    {
        return new self($context->get_required_property('offset'));
    }
}
```

This is basically a wrapper around that `$offset`, with some helpers.

There is an important concept of `combine` for StreamCursor, which you can refer to [StreamCursor](#streamcursor) section for details. This allows many streams to be used in the same template, and paginate independently of each other.

Then the `TrendingTopicStream`'s `_enumerate` method needs to be adapted as:

```php
    protected function _enumerate(int $count, StreamCursor $cursor = null, StreamTracer $tracer = null, ?EnumerationOptions $option = null): StreamResult
    {
        // if we have no cursor, assume it should start over at 0
        if (!($cursor instanceof TrendingTopicStreamCursor)) {
            $cursor = new TrendingTopicStreamCursor(0);
        }

        $offset = $cursor->getOffset();
        $topics = (new TrendingSource())->getTrendingPosts($offset, $count);

        $elements = [];
        foreach ($topics as $topic) {
            $elements[] = new TrendingTopicStreamElement(
                $topic,
                $this->get_identity(),
                new TrendingTopicStreamCursor(++$offset)
            );
        }

        return new StreamResult(count($elements) < $count, $elements);
    }
```

Notice we support pagination now, so the `StreamResult` needs to indicate the source is not exhausted when there are enough elements returned by `count($elements) < $count`.

Also each new `TrendingTopicStreamElement` also has a cursor associated with them now:

```php
$elements[] = new TrendingTopicStreamElement(
    $topic,
    $this->get_identity(),
    new TrendingTopicStreamCursor(++$offset)
);
```

With the cursor supported, the next page's request will need to pass in the corresponding `$cursor` with the `enumerate` call. Encoding the cursor to a string that can be shared with clients for subsequent requests is covered in the [StreamCursor](#streamcursor) section.

### Put components into a predefined template

Basically we will decode the YAML configuration to an array and use `StreamSerializer` to construct actual StreamBuilder components at runtime.

Let's assume `trending` is a new context that we'd like create as a template, thus we will need to create a new folder called `trending`.

Then we need to create a `awesome_trending.20230615.yml` file to define its components:

```yaml
_type: Automattic\MyAwesomeReader\StreamBuilder\Trending\Streams\TrendingTopicStream
```

We use 2 degrees of naming for templates here:

1. Context is the first degree, `trending` in our case, which is the folder name.
2. Then the final degree is `awesome_trending.20230615`. At Tumblr, we use a name like this to indicate the key change/feature of the template. It's also named with a date in case we need to iterate on the template and the date is served as a version number. But you could have any value here, this is just how we do it.

Finally, to load the template and enumerate the results in our own code, we need to call:

```php
$template = 'awesome_trending.20230615';
$meta = [];
$stream = StreamSerializer::from_template(new StreamContext(
   TemplateProvider::get_template('trending', $template),
   $meta,
   StreamBuilder::getDependencyBag()->getCacheProvider(),
   $template
));
$results = $stream->enumerate(10);
// now we can use $results in our application!
```

##### Logging & Ticks

Use `StreamBuilder::getDependencyBag()->getLog()` to log errors and ticks. You will need to implement `Tumblr\StreamBuilder\Interfaces\Log` and provide the behavior of these actions. As the most basic example, you could simply write logs to `error_log()` and view in PHP's error log.

### Let's implement more advanced options

##### What if we need to filter topics at runtime?

Let's implement a [StreamFilter](#streamfilter) to filter elements by some criteria.

A naive exmple would be:

```php
class EmptyTopicStreamFilter extends StreamElementFilter {
    /** @inheritDoc */
    protected function should_release(StreamElement $e): bool
    {
        $e = $e->get_original_element();
        if ($e instanceof TrendingTopicStreamElement) {
            // drop topics that are somehow an empty string
            return $e->get_topic() === '';
        }

        // ignore other types of stream elements
        return false;
    }
}
```

and plug it into our template

```yaml
templates:
  default:
    just_trending.20230615:
      _type: Tumblr\StreamBuilder\Streams\FilteredStream
      stream_filter:
        _type: Tumblr\StreamBuilder\StreamFilters\CompositeStreamFilter
        stream_filter_array:
          - _type: Component\Trending\StreamBuilder\StreamFilters\EmptyTopicStreamElement
      stream:
        _type: Component\Trending\StreamBuilder\Streams\TrendingTopicStream
```

And that's it, now the endpoint will filter out empty topics at runtime.

##### What if we want to combine different versions of trending topics?

We can use a [StreamCombiner](#streamcombiner) to combine different streams together. In our case we can add a `version` column to `TrendingTopicStream` which can enuemrate from different versions of trending topics.

An example template would look like

```yaml
_type: Tumblr\StreamBuilder\Streams\FilteredStream
stream_filter:
  _type: Tumblr\StreamBuilder\StreamFilters\CompositeStreamFilter
  stream_filter_array:
    - _type: Component\Trending\StreamBuilder\StreamFilters\EmptyTopicStreamElement
stream:
  _type: Tumblr\StreamBuilder\Streams\ProportionalStreamCombiner
  stream_weight_array:
    - _type: Tumblr\StreamBuilder\StreamWeight
      weight: 1
      stream:
        _type: Component\Trending\StreamBuilder\Streams\TrendingTopicStream
        version: v1
    - _type: Tumblr\StreamBuilder\StreamWeight
      weight: 1
      stream:
        _type: Component\Trending\StreamBuilder\Streams\TrendingTopicStream
        version: v2
```

And of course, we'd need to update the actual `TrendingTopicStream` to read that new `version` property from the template, via its `from_template` implementation.

##### What if we want to rank the topics?

[StreamRanker](#streamranker) is the tool we can use.

Let's also just take an example to demo how it should be plugged in:

```yaml
_type: Tumblr\StreamBuilder\Streams\FilteredStream
stream_filter:
  _type: Tumblr\StreamBuilder\StreamFilters\CompositeStreamFilter
  stream_filter_array:
    - _type: Component\Trending\StreamBuilder\StreamFilters\EmptyTopicStreamElement
stream:
  _type: Tumblr\StreamBuilder\Streams\RankedStream
  ranker: 
    _type: Tumblr\StreamBuilder\StreamRankers\RandomRanker
  inner:
    _type: Component\Trending\StreamBuilder\Streams\TrendingTopicStream
```

and that `RandomRanker`

```php
class RandomRanker extends StreamRanker
{
    /** @inheritDoc */
    protected function rank_inner(array $stream_elements, StreamTracer $tracer = null): array
    {
        shuffle($stream_elements); // the ranking!
        return $stream_elements;
    }

    /** @inheritDoc */
    public function to_template(): array
    {
        return [ '_type' => get_class($this) ];
    }

    /** @inheritDoc */
    public static function from_template(StreamContext $context)
    {
        return new self($context->get_current_identity());
    }

    /** @inheritDoc */
    protected function pre_fetch(array $elements)
    {
        // No need to do any prefetching in this example
    }
}
```

##### What if we want to inject some manual topic?

[StreamInjector](#streaminjector) is the tool to _inject_ a steam somewhere within another steam (as opposed to combining the streams).

Let's look at an example:

```yaml
    _type: Tumblr\StreamBuilder\Streams\InjectedStream
    injector:
        _type: Tumblr\StreamBuilder\StreamInjectors\GeneralStreamInjector
        allocator:
          _type: Tumblr\StreamBuilder\InjectionAllocators\GlobalFixedInjectionAllocator
          positions: [0, 10]
        inner:
          _type: Component\Trending\StreamBuilder\Streams\TrendingTopicStream
          version: v2
    stream:
        _type: Component\Trending\StreamBuilder\Streams\TrendingTopicStream
        version: v1
```

That will inject an element from the injected stream at positions 0 and 10 in the overall stream.

##### What if we want to cache a stream?

Let's say we want to cache the `TrendingTopicStream` that we built [up above](#start-with-a-very-basic-stream).

The most basic implementation involves adding a wrapper around your stream which handles the actual caching.

Let's call our implementation `CachedTrendingTopicStream`:

This, very basic, implementation simply retrieves the stream we actually want to cache and hands it off to the caching providing which is then responsible for caching the elements of the stream.

```
class CachedTrendingTopicStream extends \Tumblr\StreamBuilder\Streams\CachedStream {
	/**
	 * @inheritDoc
	 */
	public function __construct(
		\Tumblr\StreamBuilder\Stream $inner_stream,
		\Tumblr\StreamBuilder\CacheProvider $cache_provider,
		int $cache_object_type,
		int $cache_ttl,
		int $candidate_count,
		string $identity
	) {
		// Any additional handling/verification can happen here.

		// Otherwise, simply call the parent constructor.
		parent::__construct( $inner_stream, $cache_provider, $cache_object_type, $cache_ttl, $candidate_count, $identity, array() );
	}

	/**
	 * @inheritDoc
	 */
	public static function from_template( \Tumblr\StreamBuilder\StreamContext $context ) {
		$inner             = $context->deserialize_required_property( 'inner' );
		// There are built-in cache providers or you may want to use your own, custom provider.
		$cache_provider    = new \MyApplication\CacheProviders\MyCacheProvider();
		$cache_object_type = 0;
		$default_count     = $context->get_meta_by_key( 'count' ) ?? 20;
		return new self(
			$inner,
			$cache_provider,
			$cache_object_type,
			$context->get_required_property( 'cache_ttl' ),
			$context->get_optional_property( 'candidate_count', $default_count ),
			$context->get_current_identity(),
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function _slice_result_with_cursor(
		int $count,
		\Tumblr\StreamBuilder\StreamResult $inner_result,
		\Tumblr\StreamBuilder\StreamCursors\StreamCursor $cursor = null
	): \Tumblr\StreamBuilder\StreamResult {
		// No need to slice results
		return $inner_result;
	}

	/**
	 * @inheritDoc
	 */
	protected function inner_cursor( ?\Tumblr\StreamBuilder\StreamCursors\StreamCursor $cursor ): ?\Tumblr\StreamBuilder\StreamCursors\StreamCursor {
		return $cursor;
	}
}
```

Once we have the cache class, we can update our template file so the cache class wraps our original stream.

The original template looked like this:

```
_type: Automattic\MyAwesomeReader\StreamBuilder\Trending\Streams\TrendingTopicStream
```

Our updated template will look like this:

```
_type: Automattic\MyAwesomeReader\StreamBuilder\Trending\Streams\CachedTrendingTopicStream
cache_ttl: 60
inner:
	_type: Automattic\MyAwesomeReader\StreamBuilder\Trending\Streams\TrendingTopicStream
```

All we've done is moved our `TrendingTopicStream` inwards a little bit so it's wrapped by the `CachedTrendingTopicStream`.

It's also important to note that this block could easily be nested inside a more complicated template file.

## Appendix

In the appendix we cover the individual pieces of StreamBuilder.

### Stream

[Code](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/Streams/Stream.php)

A `Stream` is the most basic component of `StreamBuilder` that's used to source content.

When we extend from `Stream` we need to implement following method:

```php
protected abstract _enumerate(): StreamResult
```

This method is where we will put most of the business logic for the stream.

The basic usage of `Stream` is straightforward with this method:

```php
/** @var StreamResult $result */
$result = (new Stream(...))->enumerate(10);
```

(Note that `enumerate` is in the abstract class, `_enumerate` is what you implement in your subclass.)

It enumerates 10 elements from the stream and returns [StreamResult](#streamresult), which contains the enumerated elements which can be accessed with

```php
$elements = $result->get_elements();
```

... but it's up to you to decide how to render those individual elements to a client application.

### Templatable

[Code](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/Templatable.php)

You'll notice the majority of the components in StreamBuilder extend from `Templatable`. This abstract class is meant to make components serializable into JSON or any other text format. It has two methods:

```php
public function to_template(): array
public static function from_template(): Templatable
```

`to_template` is the method that serializes the instance into an array, which can then be serialized into JSON or another text format.

Let's take `Stream`'s implementation as an example:

```php
public function to_template(): array
{
    $base = parent::to_template();
    $base['cursor'] = (is_null($this->cursor) ? null : $this->cursor->to_template());
    $base['element_id'] = $this->get_element_id();
    return $base;
}
```

We encode everthing that is necessary to reconstruct/deserialize the object, which is often unique identifiers and context.

This is quite useful when we need to cache or transmit the object between services/languages.

Corresponding with `to_template`, the `from_template` method is used to reconstruct the object.

Let's take `DerivedStreamElement`'s implementation as example

```php
public static function from_template(StreamContext $context): self
{
    return new self(
        $context->deserialize_required_property('parent'),
        $context->get_required_property('provider_id'),
        $context->deserialize_optional_property('cursor')
    );
}
```

We are deriving necessary info from [StreamContext](#streamcontext), which contains everything you generated from the `to_template` method along with other generic information stored in a `meta` array (which can be access by `$context->get_meta()`).

### Identifiable

[Code](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/Identifiable.php)

The majority of components in StreamBuilder are associated with a thing called `identity`, which is an identifier for the type of component within a certain context, used mainly for debugging/logging.

For example, if a stream is wrapped in another stream in context `dashboard`, the identity could be like `dashboard_template_name/stream_a/stream_b` to indicates the structure of the component. If we see a `enumerate_fail` event happen with an $identity of `template_1/stream_1/after/stream2`, we can easily interpret that the Stream in `template_1`'s `PrependedStream`'s `after` stream failed.

We need the `dashboard_template_name` part because an implementation of `Stream` can be used in `dashboard` and other contexts (this is also one of the major conviences of the StreamBuilder framework, one implementation can be easily injected into different contexts).

We need the `stream_a/stream_b` part because the same components can be referenced multiple times within the same template. The nesting helps us drill down into the correct part of the template being used.

The structure is usually generated by [StreamContext::derive()](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/StreamContext.php#L124)

### StreamContext

[Code](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/StreamContext.php)

StreamContext is used to bootstrap components in StreamBuilder.

It contains 2 properties:

- Template: an array which contains the necessary information to construct `Stream`, `StreamInjector`, `StreamFilter`, etc. The fields defined in the template are predefined and static fields, which means not related to a specific request. An example would be a field that controls a `Stream` read from a certain version of the data source would be like `'version' => 'v1'`, represented as `version: v1` in the YAML. StreamContext is the only parameter in `Templatable::from_template()` so anything that is used to reconstruct the component should be in it.
- Meta: an array which contains generic information that is not static, e.g. the current User model, current timestamp, etc. Getting a meta would be like

```php
$user = $stream_context->get_meta_by_key('user');
```

### StreamResult

[Code](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/StreamResult.php)

StreamResult is a class that wraps an array of [StreamElement](#streamelement) instances.

There is also a field called `is_exhaustive` which is an indicator for other components whether they should know they can paginate the `Stream` that generates this `StreamResult`. A `StreamResult` with `is_exhaustive` equal to `true` will make `ConcatenatedStream` go to the next `Stream` to retrieve content.

### StreamElement

[Code](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/StreamElements/StreamElement.php)

StreamElement is the only element that passes around within the framework to represent the actual content in the stream.

Some key methods that we will need to implement.

```php
    public function get_element_id(): string
    public function get_original_element(): StreamElement
    public function get_parent_element(): StreamElement
    public function add_debug_info(): void
    public function get_debug_info(): array
```

These methods are something already implemented in `StreamElement`'s direct child class [`LeafStreamElement`](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/StreamElements/LeafStreamElement.php). In most cases, you will not need to have new classes extending from `StreamElement`. You can directly extend from `LeafStreamElement` for convience of these default implementations.

But we still need to take a look into some details/concepts in `StreamElement`:

- Every element has an `element_id` which is a unique identifier that is associated with each element along the whole lifecycle within StreamBuilder, which is useful for logging.
- A `StreamElement` can be either an implementation of `LeafStreamElement` or `DerivedStreamElement`, thus we have a `get_original_element` to get the `LeafStreamElement` when it's a `DerivedStreamElement`. This is useful for logging and logic with `instanceof`.
- Debug info inside each StreamElement is a generic array that carries debug information, which you can output with a StreamTracer.

So as illustrated above, a `StreamElemnt` carries a lot of information other than content itself. That info is mostly for debug, tracing, logging, ranking, etc.

### StreamCursor

[Code](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/StreamCursors/StreamCursor.php)

A `StreamCursor` is the only thing that we allow for pagination in StreamBuilder. When we think of pagination, we typically think of `offset`, `timestamp`, `after_post_id`, etc. But different streams may have different definitions of the way they want to support pagination.

With `StreamCursor`, we can make pagination generic. Let's look at the two major pieces of functionality:

- Combine: Instances of `StreamCursor` are supposed to be combinable. `cursor_a` combined with `cursor_b` will generate a new watermark `cursor_c` for pagination. (You can think it as: if the user sees `post_a` and paginates immediately, we will just use `post_a`'s cursor `cursor_a`, but if user has already seen `post_a` and `post_b`, we will use the cursor combined with `cursor_a` and `cursor_b` to paginate.) The reason we cannot directly use `cursor_b` to paginate and combine instead is because not all streams support single point cutoff.
- Encode/decode: instead of all kinds of offsets like `max_id`, `offset`, `ids`, `max_time`, `from`, etc, we use `cursor` as an encoded string of the `StreamCursor`. So the encode/decode method actually serializes/deserializes it to signle string. The default implementation will use `BinaryCodec` to encode the object with secrets, then base64-encode it to make it request-compatible.
- One cursor per stream: there is a basic assumption that a `Stream` can only return one type of `StreamCursor`.

### StreamCombiner

[Code](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/Streams/StreamCombiner.php)

This is used to mix different `Stream` contents. An example would be [ProportationalStreamCombiner](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/Streams/ProportionalStreamCombiner.php) which takes an array of `Stream` and associated weights. It will generate a feed which mixes their contents based on weights.

### StreamInjector

[Code](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/Streams/StreamCombiner.php)

This component injects one stream into another stream of content. It requires two things: a InjectionAllocator and a stream of injeciton content.

We have a default implementation of [GeneralStreamInjector](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/StreamInjectors/GeneralStreamInjector.php) which should fit most injection usage. What you need to do is implement/use an InjectionAllocator and implement a Stream for retrieving injection content.

The usage of the `GeneralStreamInjector` is like this, from the example previously.

```yaml
_type: Tumblr\StreamBuilder\Streams\InjectedStream
  injector:
  _type: Tumblr\StreamBuilder\StreamInjectors\GeneralStreamInjector
  allocator:
    _type: Tumblr\StreamBuilder\InjectionAllocators\GlobalFixedInjectionAllocator
    positions: [0, 10]
  inner:
    _type: Component\Trending\StreamBuilder\Streams\TrendingTopicStream
    version: v2
  stream:
    _type: Component\Trending\StreamBuilder\Streams\TrendingTopicStream
    version: v1
```

### InjectionAllocator

[Code](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/InjectionAllocators/InjectionAllocator.php)

This component will create injection slots based on some business logic. We have plenty of [implementations](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/InjectionAllocators) already. The logic for

```php
public function allocate(int $page_size, array $state = null): InjectionAllocatorResult
```

is usually simple. Basically it creates an `InjectionAllocatorResult` which contains an `$out` array of injection positions within the "page", and a `$state` which stores the state of this allocator for future pages' injection.

### StreamTracer

[Code](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/StreamTracers/StreamTracer.php)

This component is used to trace any event happening during the content enumeration/filtering/ranking/etc. The events we trace can be found as the constants of the `StreamTracer` class.

```php
(new Stream(...))->enumerate(10, null, new TSDBStreamTracer());
```

StreamTracers are also composable with `CompositeStreamTracer` like

```php
$tracer = new CompositeStreamTracer([
    new TSDBStreamTracer(),
    new SearchFilterDetailStreamTracer(),
]);
```

### StreamFilter

[Code](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/StreamFilters/StreamFilter.php)

StreamFilter is a very useful reuseable component to filter content in different contexts.

There are multiple layers of implementation, e.g, `StreamFilter`, `StreamElementFilter`, `PostsFilter`, mostly for convenience of some default handling of `filter_inner` method. There is a useful method called `pre_fetch` at the `StreamElementFilter` level, which is used to bulk fetch signals used for filtering, e.g, fetching note counts for posts in bulk. Fetching signals one by one for each element can be very inefficient.

A pseudocode example:

```php
/** @var Post[] $posts **/
$elements = array_map(function ($p) {
    $e = new PostStreamElement($p->id, $p->blog_id, 'test_identity');
    return $e;
}, $posts);

$result = (new FollowedBlogsOnlyFilter(null, 'test_filter'))->filter($elements);

/** @var Post[] $retained_posts **/
$retained_posts = array_map(function ($e) {
    return $e->get_post();
}, $result->get_retained());
```

There is also a `CompositeStreamFilter` to help compose an array of filters that will execute on the stream in sequence:

```php
$filter = new CompositeStreamFilter('some_identity', [
    new StreamFilters\FilterOne($identity),
    new StreamFilters\FilterTwo($identity),
    new StreamFilters\SuperSecretFilter($identity),
]);
```

The usage along with a `Stream` can wrap the Stream and filter within [FilteredStream](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/Streams/FilteredStream.php).

```yaml
templates:
  default:
    just_trending.20230615:
      _type: Tumblr\StreamBuilder\Streams\FilteredStream
      stream_filter:
        _type: Tumblr\StreamBuilder\StreamFilters\CompositeStreamFilter
        stream_filter_array:
          - _type: Component\Trending\StreamBuilder\StreamFilters\EmptyTopicStreamElement
      stream:
        _type: Component\Trending\StreamBuilder\Streams\TrendingTopicStream
```

### StreamRanker

[Code](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/StreamRankers/StreamRanker.php)

StreamRanker ranks the stream elements. [RandomRanker](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/StreamRankers/RandomRanker.php) is the simplest implementation to demo what it could do.

There is one assumption for StreamRanker: the incoming elements' count and outcoming elements' count must be the same. Filters should be used to change that count.

### CappedPostRanker

[Code](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/StreamRankers/CappedPostRanker.php)

CappedPostRanker is a ranker which will cap the number of appearances of a post which was published by a given blog until all followed blogs have caught up.

See a more detailed explanation [here](CappedPostRanker.md).

### Fencepost

[Code](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/FencepostRanking)

Fencepost is a complicated implementation to store the current user's browse history over time, such that the user will not see a shuffled history in their streams. This is usually used along with StreamRanker (as the ranker could shuffle elements' sequence and not guarantee to always give the same ranking in future requests).

You can take a look at [this](Fencepost.md) for more info about that.
