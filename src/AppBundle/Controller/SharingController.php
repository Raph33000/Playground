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


    private function sendNextSong($deviceIdentifier, $dataArray) {

        $message = new iOSMessage();
        $message->setMessage("spinextsongspotify");
        $message->setDeviceIdentifier($deviceIdentifier);
        $message->setData($dataArray);

        $this->container->get('rms_push_notifications')->send($message);
    }

    private function stopMusicNotification($deviceIdentifier) {

        $message = new iOSMessage();
        $message->setMessage("apistopsongspotify");
        $message->setDeviceIdentifier($deviceIdentifier);

        $this->container->get('rms_push_notifications')->send($message);
    }

    /**
     * @Route("/kill-room", name="kill_room")
     * @Method("POST")
     */
    public function killRoomAction(Request $request) {

        $user = $this->getUser();

        if (!$user) {
            throw new AccessDeniedException();
        }
        $em = $this->getDoctrine()->getManager();
        $roomUsers = $em->getRepository("AppBundle:User")->findBy(["current_room" => $user]);
        foreach ($roomUsers as $usr) {

            $this->stopMusicNotification($usr->getIosId());
            $usr->setCurrentRoom(null);
            $em->persist($usr);
        }
        $em->flush();
        $response = new Response($this->serialize("Room killed"), Response::HTTP_OK);
        return $this->setBaseHeaders($response);
    }

    /**
     * @Route("/stop-listening", name="stop_listening")
     * @Method("POST")
     */
    public function stopListeningAction(Request $request)
    {


        $user = $this->getUser();

        if (!$user) {
            throw new AccessDeniedException();
        }

        if (!$user->getCurrentRoom()) {

            throw new BadRequestHttpException("User is not currently listening to music.");
        }
        $em = $this->getDoctrine()->getManager();
        $user->setCurrentRoom(null);
        $em->persist($user);
        $em->flush();
        $response = new Response($this->serialize("Music stopped"), Response::HTTP_OK);
        return $this->setBaseHeaders($response);
    }

    /**
     * @Route("/next-song", name="next_song")
     * @Method("POST")
     */
    public function nextMusicSending(Request $request) {


        $user = $this->getUser();

        if (!$user) {
            throw new AccessDeniedException();
        }
        $spotifyURI = $request->request->get('spotifyURI');
        if (!$spotifyURI) {

            throw new BadRequestHttpException("Missing parameters");
        }
        $em = $this->getDoctrine()->getManager();
        $usrInRoom = $em->getRepository("AppBundle:User")->findBy(["current_room" => $user]);
        $dataArray = ["spotifyURI" => $spotifyURI, "index" => 0, "position" => 0];
        foreach ($usrInRoom as $usr) {

            if ($usr->getIosId()) {

                $this->sendNextSong($usr->getIosId(), $dataArray);
            }
        }
        $response = new Response($this->serialize("All notifications were correctly sent"), Response::HTTP_OK);
        return $this->setBaseHeaders($response);
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

                    $user->setCurrentRoom(null);
                    $usr->setCurrentRoom($user);
                    $this->sendNotificationtoIOSDevice($usr->getIosId(), $username, $dataArray);
                    $em = $this->getDoctrine()->getManager();
                    $em->persist($user);
                    $em->persist($usr);
                    $em->flush();
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