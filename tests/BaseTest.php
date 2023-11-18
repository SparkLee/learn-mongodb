<?php

namespace Tests;

use MongoDB\Client;
use MongoDB\Database;
use PHPUnit\Framework\TestCase;

class BaseTest extends TestCase
{
    protected Client $client;
    protected Database $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new Client('mongodb://127.0.0.1:27017');
        $this->db = $this->client->test;
        // $this->db=$this->client->selectDatabase('test');
    }

    public function testExample()
    {
        self::assertTrue(true);
    }
}
