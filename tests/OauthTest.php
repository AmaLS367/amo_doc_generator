<?php
use PHPUnit\Framework\TestCase;

class OauthTest extends TestCase
{
    public function testOauthFailsWithoutCode()
    {
        $result = file_get_contents('http://localhost/oauth.php');
        $this->assertNotFalse($result);
        $this->assertStringContainsString('Error', $result); // зависит от твоей обработки
    }
}
