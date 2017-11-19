<?php
/**
 * Created by PhpStorm.
 * User: raphaelperchec
 * Date: 11/16/17
 * Time: 9:23 AM
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


class FriendsController extends FOSRestController
{

    use \AppBundle\Helper\ControllerHelper;


    private function sendNotificationtoIOSDevice($deviceIdentifier, $username, $message) {


        $message = new iOSMessage();
        $message->setMessage(ucfirst($username) . ' ' . $message);
        $message->setDeviceIdentifier($deviceIdentifier);

        $this->container->get('rms_push_notifications')->send($message);
    }

    /**
     * @Route("/get-friends", name="get_friends")
     * @Method("POST")
     */
    public function getFriendsAction(Request $request) {

        $user = $this->getUser();
        if (!$user) {
            throw new AccessDeniedException();
        }
        $friendList = $user->getMyFriends();
        $formattedArray = Array();
        foreach ($friendList as $key => $friend) {

            $formattedArray[$key]["id"] = $friend->getId();
            $formattedArray[$key]["username"] = $friend->getUsername();
            $formattedArray[$key]["friendsTogether"] =  (in_array($friend, $user->getMyFriends()->toArray()) and in_array($friend, $user->getFriendsWithMe()->toArray())) ? true : false;

        }
        $response = new Response($this->serialize($formattedArray), Response::HTTP_OK);

        return $this->setBaseHeaders($response);
    }

    /**
     * @Route("/get-friend-requests", name="get_friend_requests")
     * @Method("POST")
     */
    public function getFriendRequestsAction(Request $request)
    {

        $user = $this->getUser();
        if (!$user) {
            throw new AccessDeniedException();
        }

        $friendRequests = array();
        foreach ($user->getFriendsWithMe() as $val) {

            if (!in_array($val, $user->getMyFriends()->toArray())) {

                $friendRequests[] = ["id" => $val->getId(), "username" => $val->getUsername()];
            }
        }
        $response = new Response($this->serialize($friendRequests), Response::HTTP_OK);

        return $this->setBaseHeaders($response);

    }

    private function isInIdList($userlist, $id) {

        foreach ($userlist as $key => $value) {

            if ($value->getId() == $id) {

                return true;
            }
        }
        return false;
    }

    /**
     * @Route("/search-user", name="search_user")
     * @Method("POST")
     */
    public function searchUserAction(Request $request)
    {
        $user = $this->getUser();
        if (!$user) {
            throw new AccessDeniedException();
        }
        $em = $this->getDoctrine()->getManager();
        $name = $request->request->get("username");
        if (!$name) {

            throw new BadRequestHttpException("Missing parameter");
        }
        $connection = $em->getConnection();
        $statement = $connection->prepare("SELECT * FROM users as usr WHERE usr.username LIKE '" . $name . "%' ORDER BY CHAR_LENGTH(usr.username);");
        $statement->execute();
        $userList = $statement->fetchAll();
        $jsonarray = Array();
        $friendList = $user->getMyFriends();
        foreach ($userList as $key => $result) {

            if ($result != $user) {

                $jsonarray[$key]["username"] = $result["username"];
                $jsonarray[$key]["isFriend"] = $this->isInIdList($friendList, $result["id"]);
                $jsonarray[$key]["isFriendTogether"] = (in_array($result, $user->getMyFriends()->toArray()) and in_array($result, $user->getFriendsWithMe()->toArray())) ? true : false;
            }
        }

        $response = new Response($this->serialize($jsonarray), Response::HTTP_OK);

        return $this->setBaseHeaders($response);
    }

    /**
     * @Route("/add-friend", name="add_friend")
     * @Method("POST")
     */
    public function addFriendAction(Request $request) {


        $user = $this->getUser();
        if (!$user) {
            throw new AccessDeniedException();
        }
        $em = $this->getDoctrine()->getManager();
        $username = $request->request->get("username");
        if (!$username) {
            throw new BadRequestHttpException();
        }
        $newFriend = $em->getRepository("AppBundle:User")->findOneByUsername($username);
        if (!$newFriend) {
            throw new BadRequestHttpException("User doesn't exist");
        }
        if ($newFriend == $user) {

            throw new BadRequestHttpException("You can't be friend with yourself.");
        }

        $friendList = $user->getMyFriends();
        if (!in_array($newFriend, $friendList->toArray())) {

            if (!in_array($newFriend, $user->getFriendsWithMe()->toArray())) {

                if ($newFriend->getIosId()) {

                    $this->sendNotificationtoIOSDevice($newFriend->getIosId(), $user->getUsername(), "wants to be connected with you!");
                }
            }
            else {
                if ($newFriend->getIosId()) {
                    $this->sendNotificationtoIOSDevice($newFriend->getIosId(), $user->getUsername(), "is now connected with you!");
                }
            }
            $user->addMyFriend($newFriend);
            $em->persist($user);
            $em->flush();
            $response = new Response($this->serialize("Friend added"), Response::HTTP_OK);
            return $this->setBaseHeaders($response);
        }


        $response = new Response($this->serialize("Friend already in friend list"), Response::HTTP_OK);

        return $this->setBaseHeaders($response);
    }

    /**
     * @Route("/remove-friend", name="remove_friend")
     * @Method("POST")
     */
    public function removeFriendAction(Request $request)
    {


        $user = $this->getUser();
        if (!$user) {
            throw new AccessDeniedException();
        }
        $em = $this->getDoctrine()->getManager();
        $username = $request->request->get("username");
        if ($username == null) {
            throw new BadRequestHttpException("Missing parameter");
        }
        $toRemove = $em->getRepository("AppBundle:User")->findOneByUsername($username);
        if (!$toRemove) {
            throw new BadRequestHttpException("User doesn't exists");
        }
        if (!in_array($toRemove, $user->getMyFriends()->toArray())) {
            throw new BadRequestHttpException("User is not in friend list");
        }
        $user->removeMyFriend($toRemove);
        $em->persist($user);
        $em->flush();
        $response = new Response($this->serialize("Friend successfully removed"), Response::HTTP_OK);

        return $this->setBaseHeaders($response);
    }
}