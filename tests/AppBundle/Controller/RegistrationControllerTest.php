<?php

// tests/UserBundle/Controller/RegistrationControllerTest.php

namespace Test\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegistrationControllerTest extends WebTestCase
{
    public function testPostRegisterNewUser()
    {
        $data = [
            'username' => 'matko',
            'email' => 'matko@gmail.com',
            'plainPassword' => [
                'first' => 'test123', 'second' => 'test123'
            ]
        ];

        $client = $this->makePOSTRequest($data);

        $this->assertEquals(201, $client->getResponse()->getStatusCode());
    }

    public function testPostRegisterNewUserWithInvalidEmail()
    {
        $data = [
            'username' => 'matko',
            'email' => 'matkasgasgashgamail.com',
            'plainPassword' => [
                'first' => 'test123', 'second' => 'test123'
            ]
        ];

        $client = $this->makePOSTRequest($data);

        $this->assertEquals(400, $client->getResponse()->getStatusCode());
    }

    private function makePOSTRequest($data)
    {
        $client = static::createClient();
        $client->request(
            'POST', '/playground/register', array(), array(),
            array(
                'CONTENT_TYPE' => 'application/json',
            ),
            json_encode($data)
        );

        return $client;
    }
}