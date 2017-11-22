<?php
/**
 * Created by PhpStorm.
 * User: raphaelperchec
 * Date: 11/19/17
 * Time: 8:56 AM
 */

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class UserController extends Controller
{

    use \AppBundle\Helper\ControllerHelper;

    private function isInIdList($userlist, $id) {

        foreach ($userlist as $key => $value) {

            if ($value->getId() == $id) {

                return true;
            }
        }
        return false;
    }

    /**
     * @Route("/upload-picture", name="upload_picture")
     * @Method("POST")
     */
    public function uploadPictureAction(Request $request)
    {

        $user = $this->getUser();
        if (!$user) {
            throw new AccessDeniedException();
        }
        $file = $request->files->get("profilePicture");

        $info = getimagesize($file);
        list($x, $y) = $info;
        if (strpos("x" . $file->getMimeType(), "image") == false) {

            throw new BadRequestHttpException("Incorrect file type.");
        }
        if ($x != $y or $x < 45 or $x > 50) {
            throw new BadRequestHttpException("Incorrect picture dimensions.");
        }
        $fileName = md5(uniqid()).'.'.$file->guessExtension();

        $file->move(
            $this->getParameter('profile_pictures_directory'),
            $fileName
        );

        if ($user->getProfilePicture() and file_exists($this->getParameter("profile_pictures_directory") . "/". $user->getProfilePicture())) {

            unlink($this->getParameter("profile_pictures_directory") . "/". $user->getProfilePicture());
        }

        $user->setProfilePicture($fileName);
        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->flush();
        $response = new Response($this->serialize("Picture successfully uploaded."), Response::HTTP_OK);
        return $this->setBaseHeaders($response);

    }

    /**
     * @Route("/get-user", name="get_user")
     * @Method("POST")
     */
    public function getUserAction(Request $request)
    {

        $user = $this->getUser();
        if (!$user) {
            throw new AccessDeniedException();
        }
        $username = $request->request->get("username");
        if (!$username) {

            throw new BadRequestHttpException("Missing parameters.");
        }
        $usr = $this->getDoctrine()->getManager()->getRepository("AppBundle:User")->findOneByUsername($username);
        if (!$usr) {
            throw new BadRequestHttpException("User doesn't exist.");
        }
        $tosend = array();
        $tosend["username"] = $usr->getUsername();
        $tosend["firstname"] = $usr->getFirstname();
        $tosend["lastname"] = $usr->getLastname();
        $tosend["isFriendTogether"] = ($this->isInIdList($user->getMyFriends(), $usr->getId()) and $this->isInIdList($user->getFriendsWithMe(), $usr->getid())) ? true : false;
        $tosend["picture"] = $request->getHost() . "/" . $this->getParameter("profile_pictures_directory") . "/". $usr->getProfilePicture();
        $response = new Response($this->serialize($tosend), Response::HTTP_OK);
        return $this->setBaseHeaders($response);
    }

}