<?php

namespace Tests;

use MongoDB\Collection;
use MongoDB\Model\BSONArray;

class AggregationTest extends BaseTest
{
    private Collection $authors;
    private Collection $books;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authors = $this->db->authors;
        $this->books = $this->db->books;

        // 清空集合
        $this->books->drop();
        $this->authors->drop();
    }

    /**
     * @see https://www.mongodb.com/docs/manual/reference/operator/aggregation/out/ $out (aggregation)
     * @return void
     */
    public function testOut()
    {
        // Given
        $this->books->insertMany([
            ["_id" => 8751, "title" => "The Banquet", "author" => "Dante", "copies" => 2, 'price' => 100],
            ["_id" => 8752, "title" => "Divine Comedy", "author" => "Dante", "copies" => 1, 'price' => 200],
            ["_id" => 8645, "title" => "Eclogues", "author" => "Dante", "copies" => 2, 'price' => 300],
            ["_id" => 7000, "title" => "The Odyssey", "author" => "Homer", "copies" => 10, 'price' => 400],
            ["_id" => 7020, "title" => "Iliad", "author" => "Homer", "copies" => 10, 'price' => 500],
        ]);

        // When
        $this->books->aggregate(
            [
                ['$group' => [
                    '_id' => '$author',
                    'count' => ['$sum' => 1],
                    'totalCopies' => ['$sum' => '$copies'],
                    'avgPrice' => ['$avg' => '$price'],
                    'books' => ['$push' => '$title'],
                ]],
                ['$sort' => ['totalCopies' => -1]],
                ['$out' => 'authors'],
            ]
        );

        // Then
        $authors = $this->authors->find()->toArray();
        self::assertCount(2, $authors);
        // author0
        $author0 = $authors[0];
        self::assertSame('Homer', $author0['_id']);
        self::assertSame(2, $author0['count']);
        self::assertSame(20, $author0['totalCopies']);
        self::assertSame(450.0, $author0['avgPrice']);
        self::assertInstanceOf(BSONArray::class, $author0->books);
        self::assertSame(['The Odyssey', 'Iliad'], (array)$author0['books']);
        self::assertSame(['The Odyssey', 'Iliad'], (array)$author0['books']);
        // author1
        $author1 = $authors[1];
        self::assertSame('Dante', $author1->_id);
        self::assertSame(3, $author1->count);
        self::assertSame(5, $author1->totalCopies);
        self::assertSame(200.0, $author1->avgPrice);
        self::assertInstanceOf(BSONArray::class, $author1->books);
        self::assertSame(['The Banquet', 'Divine Comedy', 'Eclogues'], (array)$author1['books']);
    }
}
