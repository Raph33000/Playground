<?php

// src/AppBundle/Controller/ApiController.php

namespace AppBundle\Controller;

use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations\Route;

class ApiController extends FOSRestController
{

    public function getSecureResourceAction()
    {
        if (false === $this->get('security.context')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw new AccessDeniedException();
        }
    }

    /**
     * @Route("/api")
     */
    public function indexAction()
    {
        $user = $this->get('security.token_storage')->getToken()->getUser();


        $data = array("hello" => "world");
        $view = $this->view($data);
        return $this->handleView($view);
    }
}