<?php

namespace AppBundle\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use AppBundle\Entity\User;

class RequestListener
{
    private $em;
    private $securityToken;
    private $dispatcher;

    public function __construct(EntityManager $em, $securityToken, $dispatcher)
    {
        $this->em = $em;
        $this->securityToken = $securityToken;
        $this->dispatcher = $dispatcher;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
        return;
        }
        $token = $event->getRequest()->request->get("token");
        if ($token == null) {
            return;
        }
        $user = $this->em->getRepository("AppBundle:User")->findOneByToken($token);
        if ($user == null) {
            return;
        }
        $token = new UsernamePasswordToken($user, $user->getPassword(), "public", $user->getRoles());

        // For older versions of Symfony, use security.context here
        $this->securityToken->setToken($token);

        // Fire the login event
        // Logging the user in above the way we do it doesn't do this automatically
        $event = new InteractiveLoginEvent($event->getRequest(), $token);
        $this->dispatcher->dispatch("security.interactive_login", $event);

    }
}