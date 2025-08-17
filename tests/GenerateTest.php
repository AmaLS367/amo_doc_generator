<?php
use PHPUnit\Framework\TestCase;

class GenerateTest extends TestCase
{
    public function testGenerateWithValidInput()
    {
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $data = [
            'lead_id' => 123456,
            'template' => 'order',
            'discount' => 0,
            'products' => [
                [
                    'name' => 'Test Service',
                    'unit_price' => 1000,
                    'qty' => 1,
                    'discount_percent' => 0
                ]
            ]
        ];

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-type: application/json",
                'content' => json_encode($data)
            ]
        ]);

        $result = file_get_contents('http://localhost/api/generate.php', false, $context);
        $this->assertNotFalse($result);
        $json = json_decode($result, true);
        $this->assertArrayHasKey('url', $json);
        $this->assertStringContainsString('.docx', $json['url']);
    }
}
