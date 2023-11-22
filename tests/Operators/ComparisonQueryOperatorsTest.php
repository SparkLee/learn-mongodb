<?php

namespace Tests\Operators;

use MongoDB\Collection;
use Tests\BaseTest;

class ComparisonQueryOperatorsTest extends BaseTest
{
    private Collection $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventory = $this->db->inventory;

        // 清空集合
        $this->inventory->drop();
    }

    public function testComparisonQueryOperators()
    {
        $this->inventory->insertMany([
            ["category" => "cat1", "color" => "color1", "item" => "item1", "price" => 19.9, "age" => 2, "amount" => 6.6, "brand" => "brand1", "quantity" => 350, "tags" => ["school", "office"]],
            ["category" => "cat1", "color" => "color1", "item" => "item2", "price" => 19.9, "age" => 1, "amount" => 6.6, "quantity" => 5, "tags" => ["school", "storage", "home"]],
            ["category" => "cat1", "color" => "color2", "item" => "item3", "price" => 19.9, "age" => 3, "amount" => 6.6, "brand" => "brand1", "quantity" => 15, "tags" => ["school", "home"]],
            ["category" => "cat2", "color" => "color2", "item" => "item4", "price" => 9.99, "age" => 3, "amount" => 6.6, "brand" => "brand2", "tags" => ["office", "storage", "mall"]],
        ]);
        $cursor = $this->inventory->find([
            'category' => 'cat1',                    // 相等
            'color' => ['$eq' => 'color1'],          // 相等
            'brand' => ['$ne' => 'brand2'],          // 不等：字段值不相等，或字段压根不存在
            'price' => ['$gt' => 10],                // 大于
            'quantity' => ['$gte' => 5],             // 大于等于
            'age' => ['$lt' => 3],                   // 小于
            'amount' => ['$lte' => 8.8],             // 小于等于
            'item' => ['$in' => ['item1', 'item2']], // In
            'tags' => ['$nin' => ['mall']],          // Not In
        ]);
        $result = $cursor->toArray();
        // var_dump($result);
        self::assertCount(2, $result);
    }

    public function testLogicalQueryOperators_not()
    {
        $this->inventory->insertMany([
            ["color" => "red"],
            ["brand" => "iphone", "color" => "green"],
            ["brand" => "android", "color" => "black"],
        ]);
        $cursor = $this->inventory->find(
            [
                'brand' => [
                    // '$not' => ['$in' => ['iphone', 'ipad']],
                    '$not' => ['$regex' => '^i.*'], // 使用正规表达式，匹配：brand 不是以 i 开头的文档
                ]
            ],
            [
                'sort' => ['color' => 1],
            ]
        );
        $result = $cursor->toArray();
        self::assertCount(2, $result);
        self::assertSame('black', $result[0]['color']);
        self::assertSame('red', $result[1]['color']); // $not 运算符会包含字段不存在的情况
    }
}