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

namespace Test\Tumblr\StreamBuilder\StreamCursors;

use Test\Mock\Tumblr\StreamBuilder\Interfaces\TestContextProvider;
use Tests\Unit\Tumblr\StreamBuilder\StreamBuilderTest;
use Tumblr\StreamBuilder\DependencyBag;
use Tumblr\StreamBuilder\Interfaces\Credentials;
use Tumblr\StreamBuilder\Interfaces\Log;
use Tumblr\StreamBuilder\StreamCursors\MultiCursor;
use Tumblr\StreamBuilder\StreamCursors\StreamCursorSerializer;
use Tumblr\StreamBuilder\TransientCacheProvider;

/**
 * Some tests for StreamCursorSerializer
 */
class StreamCursorSerializerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|(\Tumblr\StreamBuilder\Interfaces\Credentials&\PHPUnit\Framework\MockObject\MockObject)
     */
    private $creds;
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|(Log&\PHPUnit\Framework\MockObject\MockObject)
     */
    private $log;

    /**
     * @var DependencyBag The bag.
     */
    private DependencyBag $bag;

    /**
     * Setup.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->initStreamBuilder();
    }

    /**
     * Data provider for testDefaltedCursor
     * @return array[]
     */
    public function invalidCursorProvider(): array
    {
        $this->initStreamBuilder();
        return [
            [
                StreamCursorSerializer::encodeCursor(
                    new MultiCursor(['amazing_stream' => new MultiCursor([])]),
                    '100'
                ),
                null,
                'cursor_cannot_inflate',
            ],
            [
                StreamCursorSerializer::encodeCursor(
                    new MultiCursor(['amazing_stream' => new MultiCursor([])]),
                    null
                ),
                100,
                null,
            ],
        ];
    }

    /**
     * @dataProvider invalidCursorProvider
     * @param string $cursor Cursor string
     * @param int|null $user_id User id
     * @param string|null $exception Expected exception.
     */
    public function testDefaultedCursor(string $cursor, ?int $user_id, ?string $exception): void
    {
        if ($exception) {
            $this->expectExceptionLog();
        }

        $cursor = StreamCursorSerializer::decodeCursor($cursor, $user_id === null ? null : (string) $user_id);
        if ($exception === null) {
            $this->assertNotNull($cursor);
        }
    }

    /**
     * Inject a mocked log and expect that $log->exception() will be called.
     * @return void
     */
    private function expectExceptionLog()
    {
        $this->log->expects($this->once())->method('exception');
    }

    /**
     * Set fake credentials.
     * @return void
     */
    public function setCredentialKeys(): void
    {
        $this->creds = $this->getMockBuilder(Credentials::class)->getMock();
        $this->creds->method('get')
            ->willReturn('secret');
    }

    /**
     * @return void
     */
    protected function initStreamBuilder(): void
    {
        $this->log = $this->getMockBuilder(Log::class)->getMock();
        $this->setCredentialKeys();
        $this->bag = new DependencyBag(
            $this->log,
            new TransientCacheProvider(),
            $this->creds,
            new TestContextProvider()
        );
        StreamBuilderTest::overrideStreamBuilderInit($this->bag);
    }
}
