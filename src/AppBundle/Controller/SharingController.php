<?php
/**
 * Created by PhpStorm.
 * User: raphaelperchec
 * Date: 11/15/17
 * Time: 2:30 PM
 */

namespace AppBundle\Controller;


use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use RMS\PushNotificationsBundle\Message\iOSMessage;


class SharingController extends FOSRestController
{
    use \AppBundle\Helper\ControllerHelper;

    public function getSecureResourceAction()
    {
        if (false === $this->get('security.context')->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw new AccessDeniedException();
        }
    }

    private function checkListValidity($contactList, $user) {


        $em = $this->getDoctrine()->getManager();
        $friendList = $user->getMyFriends();
        $toShare = $em->getRepository("AppBundle:User")->findByUsername($contactList);
        $friends = array();
        foreach ($toShare as $val) {

            if (in_array($val, $friendList->toArray())) {

                $friends[] = $val;
            }
        }
        return $friends;
    }

    private function sendNotificationtoIOSDevice($deviceIdentifier, $username, $dataArray) {


        $message = new iOSMessage();
        $message->setMessage(ucfirst($username) . ' wants to share some good music with you!');
        $message->setDeviceIdentifier($deviceIdentifier);
        $message->setData($dataArray);

        $this->container->get('rms_push_notifications')->send($message);
    }

    /**
     * @Route("/share-music", name="share_music")
     * @Method("POST")
     */
    public function shareMusicToContactsAction(Request $request) {

        $user = $this->getUser();

        if (!$user) {
            throw new AccessDeniedException();
        }
        $username = $user->getUsername();
        $contactList = $request->request->get('toShare');
        $spotifyURI = $request->request->get('spotifyURI');
        $position = $request->request->get('position');
        if (!$contactList or !$spotifyURI) {

            throw new BadRequestHttpException("Missing parameters");
        }

        $dataArray = ["spotifyURI" => $spotifyURI, "index" => 0, "position" => $position];
        $errorString = "";
        $toShare = $this->checkListValidity($contactList, $user);
        foreach ($toShare as $usr) {

            if (in_array($usr, $user->getMyFriends()->toArray()) and in_array($usr, $user->getFriendsWithMe()->toArray())) {

                if ($usr->getIosId()) {

                    $this->sendNotificationtoIOSDevice($usr->getIosId(), $username, $dataArray);
                }
            }
            else {

                if ($errorString == "") {
                    $errorString = "User(s)";
                }
                $errorString .= " " . $usr->getUsername();
            }
        }
        if ($errorString != "") {

            $response = new Response($this->serialize($errorString . " are not friend with you."), Response::HTTP_OK);
        }
        else {

            $response = new Response($this->serialize("All notifications were correctly sent"), Response::HTTP_OK);
        }

        return $this->setBaseHeaders($response);
    }


}