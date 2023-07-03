<?php declare(strict_types=1);

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

namespace Test\Tumblr\StreamBuilder\StreamRankers;

use Tumblr\StreamBuilder\Helpers;
use Tumblr\StreamBuilder\SignalFetchers\CompositeSignalFetcher;
use Tumblr\StreamBuilder\SignalFetchers\SignalBundle;
use Tumblr\StreamBuilder\SignalFetchers\SignalFetcher;
use Tumblr\StreamBuilder\StreamContext;
use Tumblr\StreamBuilder\StreamElements\LeafStreamElement;
use Tumblr\StreamBuilder\StreamElements\StreamElement;
use Tumblr\StreamBuilder\StreamRankers\SingleSignalStreamRanker;
use Tumblr\StreamBuilder\StreamRankers\StreamRanker;
use Tumblr\StreamBuilder\StreamTracers\StreamTracer;
use const Tumblr\StreamBuilder\QUERY_SORT_ASC;
use const Tumblr\StreamBuilder\QUERY_SORT_DESC;

/**
 * Class SingleSignalStreamRankerTest
 */
class SingleSignalStreamRankerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test rank
     * @return SingleSignalStreamRanker
     */
    public function test_rank()
    {
        /** @var StreamElement $e1 */
        $e1 = $this->getMockBuilder(LeafStreamElement::class)->disableOriginalConstructor()->getMockForAbstractClass();
        /** @var StreamElement $e2 */
        $e2 = $this->getMockBuilder(LeafStreamElement::class)->disableOriginalConstructor()->getMockForAbstractClass();

        $sb = new SignalBundle($sig1 = [
            Helpers::memory_element_id($e1) => [ 's1' => 123, 's2' => 'abc' ],
            Helpers::memory_element_id($e2) => [ 's1' => 456, 's2' => 'def' ],
        ]);

        /** @var SignalFetcher|\PHPUnit\Framework\MockObject\MockObject $signal_fetcher */
        $signal_fetcher = $this->getMockBuilder(SignalFetcher::class)
            ->setConstructorArgs(['fetcher'])
            ->getMockForAbstractClass();
        $signal_fetcher->expects($this->exactly(2))
            ->method('fetch_inner')
            ->willReturn($sb);

        $tracer = $this->getMockBuilder(StreamTracer::class)->disableOriginalConstructor()->getMockForAbstractClass();

        $ranker = new SingleSignalStreamRanker($signal_fetcher, 's1', QUERY_SORT_DESC, 'amazing_ranker');
        $ranked = $ranker->rank([$e1, $e2], $tracer);
        $this->assertSame([$e2, $e1], $ranked);

        $ranker = new SingleSignalStreamRanker($signal_fetcher, 's1', QUERY_SORT_ASC, 'amazing_ranker');
        $ranked = $ranker->rank([$e1, $e2], $tracer);
        $this->assertSame([$e1, $e2], $ranked);

        return $ranker;
    }

    /**
     * Test rank call failed.
     */
    public function test_fail_rank()
    {
        $this->expectException(\Exception::class);

        /** @var SingleSignalStreamRanker|\PHPUnit\Framework\MockObject\MockObject $ranker */
        $ranker = $this->getMockBuilder(StreamRanker::class)->setConstructorArgs(['amazing_ranker'])->getMockForAbstractClass();
        $ranker->expects($this->once())
            ->method('rank_inner')
            ->willThrowException(new \Exception('whoops'));

        $tracer = $this->getMockBuilder(StreamTracer::class)->disableOriginalConstructor()->getMockForAbstractClass();

        $ranker->rank([], $tracer);
    }

    /**
     * Test with ranker with invalid ordering mode.
     */
    public function test_invalid_ordering_mode()
    {
        $this->expectException(\InvalidArgumentException::class);

        /** @var SignalFetcher|\PHPUnit\Framework\MockObject\MockObject $signal_fetcher */
        $signal_fetcher = $this->getMockBuilder(SignalFetcher::class)->disableOriginalConstructor()->getMockForAbstractClass();
        new SingleSignalStreamRanker($signal_fetcher, 'bar', 'AMAZING_MODE', 'amazing_ranker');
    }

    /**
     * Test get identity
     * @depends test_rank
     * @param SingleSignalStreamRanker $ranker The ranker passed around.
     */
    public function test_get_identity(SingleSignalStreamRanker $ranker)
    {
        $this->assertSame('amazing_ranker[SingleSignalStreamRanker]', $ranker->get_identity(true));
        $this->assertSame('amazing_ranker', $ranker->get_identity());
    }

    /**
     * Test to template
     */
    public function test_to_template()
    {
        $template = [
            '_type' => SingleSignalStreamRanker::class,
            'signal_fetcher' => [
                '_type' => CompositeSignalFetcher::class,
                'fetchers' => [],
            ],
            'signal_key' => 'bar',
            'ordering_mode' => QUERY_SORT_DESC,
        ];
        $signal_fetcher = new CompositeSignalFetcher([], 'amazing_fetcher');
        $ranker = new SingleSignalStreamRanker($signal_fetcher, 'bar', QUERY_SORT_DESC, 'amazing_ranker');
        $this->assertSame($template, $ranker->to_template());

        return $template;
    }

    /**
     * @depends test_to_template
     * @param array $template Ranker template
     * Test from template
     */
    public function test_from_template(array $template)
    {
        $context = new StreamContext($template, []);
        $this->assertSame($template, SingleSignalStreamRanker::from_template($context)->to_template());
    }
}
