<?php
use PHPUnit\Framework\TestCase;

class PrefillTest extends TestCase
{
    public function testPrefillReturnsData()
    {
        $leadId = 123456;
        $result = file_get_contents("http://localhost/api/prefill.php?lead_id=$leadId");
        $this->assertNotFalse($result);
        $data = json_decode($result, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('products', $data);
    }
}
