<?php

namespace AppBundle\Controller;

use AdminBundle\Form\UserType;
use AppBundle\Entity\Address;
use AppBundle\Entity\Interest;
use AppBundle\Entity\InterestUser;
use AppBundle\Entity\Phone;
use AppBundle\Entity\Tag;
use AppBundle\Form\AddressType;
use AppBundle\Form\ProfileType;
use AppBundle\Form\TagType;
use AppBundle\Form\UserEmailType;
use AppBundle\Form\UserPasswordType;
use AppBundle\Form\UserPhoneType;
use BehaviorFixtures\ORM\UserEntity;
use Doctrine\ORM\EntityManager;
use FOS\RestBundle\Controller\Annotations\Route;
use FOS\UserBundle\Propel\UserManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;

use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;

use AppBundle\Entity\User;
use AppBundle\Component\Api\ApiResponseWrap ;
use AppBundle\Component\Api\ApiResponseErrorWrap;
use BaseBundle\Controller\BaseController;

class ProfileController extends BaseController
{
    /**
     * @Post("/profile/register", name="_profileRegister")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function registerAction(Request $request)
    {

        $user = new User();

        $form = $this->createForm(ProfileType::class, $user, ['csrf_protection' => false]);
        $form->handleRequest($request);
        $data = json_decode($request->getContent(), true);
        $form->submit($data);

        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors() as $key => $error) {
                if ($form->isRoot()) {
                    $errors['#'][] = $error->getMessage();
                } else {
                    $errors[] = $error->getMessage();
                }
            }
            if(count($errors) >0){
                return $this->handleView($this->view(
                    ApiResponseWrap::error(
                        ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                        $errors['#']
                    ), 500)
                );
            }
        }

        $errors = $this->validateEntity($user);
        if(count($errors)){
            foreach($errors as $key => $error){
                if($error === "This value is already used." || $error === "Ta wartość jest już wykorzystywana."){
                    $errors[$key] = "User already exists.";
                }
            }
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                    $errors
                ), 500)
            );
        }
        $user->setUsername($form['email']->getData())
            ->setPassword(uniqid())
            ->setRegistrationStep(1);

        $userManager = $this->get('fos_user.user_manager');
        try {
            $userManager->updateCanonicalFields($user);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();
        } catch (\Exception $e) {
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_APPLICATION_EXCEPTION,
                    ApiResponseErrorWrap::MESSAGE_SOMETHING_WENT_WRONG
                ), 500)
            );
        }

        $response = $this->forward('user.resetting:sendEmailManualAction', array("request"=>$request,"user"=>$user))->getContent();

        if(!$response){
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_APPLICATION_EXCEPTION,
                    "Mail doesn't send"
                ), 500)
            );
        }
        return $this->handleView(
            $this->view(ApiResponseWrap::success([
                'id' => $user->getId()
            ], $this->get('jms_serializer')))
        );
    }

    /**
     * @Put("/profile/disable", name="_profileDisable")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function disableAction()
    {
        $user = $this->getUser();
        $user->setEnabled(false);

        try {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();
        } catch (\Exception $e) {
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_APPLICATION_EXCEPTION,
                    ApiResponseErrorWrap::MESSAGE_SOMETHING_WENT_WRONG,
                    $this->get('jms_serializer')
                ), 500)
            );
        }

        return $this->handleView(
            $this->view(ApiResponseWrap::success([], $this->get('jms_serializer')))
        );
    }

    /**
     * @Get("/profile/applications", name="_profileApplications")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function applicationsAction()
    {
        $user = $this->getUser();

        /**
         * @var $repository User
         */
        $repository = $this->getDoctrine()->getRepository('AppBundle:User')->findApplicationsByUser($user);
        return $this->handleView(
            $this->view(ApiResponseWrap::success($repository, $this->get('jms_serializer')))
        );
    }

    /**
     * @Post("/profile/address", name="_profileAddresses")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function adressesAction(Request $request)
    {
        $address = new Address();

        $form = $this->createForm(AddressType::class, $address, ['csrf_protection' => false]);
        $form->handleRequest($request);
        $data = json_decode($request->getContent(), true);

        $form->submit($data);
        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors() as $key => $error) {
                if ($form->isRoot()) {
                    $errors['#'][] = $error->getMessage();
                } else {
                    $errors[] = $error->getMessage();
                }
            }
            if(count($errors) >0){
                return $this->handleView($this->view(
                    ApiResponseWrap::error(
                        ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                        $errors['#']
                    ), 500)
                );
            }
        }
        $errors = $this->validateEntity($address);
        if(count($errors)){
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                    $errors
                ), 500)
            );
        }
        $entityManager = $this->getDoctrine()->getManager();
        try {
            $user = $this->getUser();
            $address->setUser($user);
            $entityManager->persist($address);
            $entityManager->flush();
        } catch (\Exception $e) {
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_APPLICATION_EXCEPTION,
                    $e->getMessage()
                ), 500)
            );
        }

        return $this->handleView(
            $this->view(ApiResponseWrap::success([
                'id' => $address->getId()
            ], $this->get('jms_serializer')))
        );

    }


    /**
     * @Post("/profile/{npid}/default-data", name="_profileDefaultData")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function updateDefaultDataAction(Request $request)
    {
        $id = $request->get('npid');

        $data = json_decode($request->getContent(), true);
        /**
         * @var $user User
         */
        $user = $this->getDoctrine()
            ->getRepository('AppBundle\Entity\User')->findOneBy(["npid"=>$id]);
        if(is_null($user)){
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                    ["User not found"]
                ), 404)
            );
        }
        if(!$user->getLicenseToPracticeConfirmed()){
            $user->setLicenseToPracticeConfirmed(false);
        } else {
            $user->setLicenseToPracticeConfirmed(true);
        }
        $form = $this->createForm(ProfileType::class, $user, ['csrf_protection' => false]);
        $data['email'] = $user->getEmail();
        if(!isset($data['name'])) $data['name'] = $user->getName();
        if(!isset($data['surname'])) $data['surname'] = $user->getSurname();
        if(!isset($data['licenseToPractice'])) $data['licenseToPractice'] = $user->getLicenseToPractice();
        $form->handleRequest($request);

        $form->submit($data);
        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors() as $key => $error) {
                if ($form->isRoot()) {
                    $errors['#'][] = $error->getMessage();
                } else {
                    $errors[] = $error->getMessage();
                }
            }
            if(count($errors) >0){
                return $this->handleView($this->view(
                    ApiResponseWrap::error(
                        ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                        $errors['#']
                    ), 500)
                );
            }
        }

        $errors = $this->validateEntity($user);
        if(count($errors)){
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                    $errors
                ), 500)
            );
        }

        $entityManager = $this->getDoctrine()->getManager();
        try {
            $user = $this->getUser();
            $entityManager->persist($user);
            $entityManager->flush();
        } catch (\Exception $e) {
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_APPLICATION_EXCEPTION,
                    $e->getMessage()
                ), 500)
            );
        }
        
        return $this->handleView(
            $this->view(ApiResponseWrap::success([
                'id' => $user->getId()
            ], $this->get('jms_serializer')))
        );

    }

    /**
     * @Post("/profile/{npid}/reset-password/reset", name="_profileResetPassword")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function resetPasswordAction(Request $request){

        $id = $request->get('npid');

        $data = json_decode($request->getContent(), true);
        if(!isset($data['confirmationToken'])){
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                    ["Empty confirmation token"]
                ), 500)
            );
        }
        /**
         * @var $user User
         */
        $user = $this->getDoctrine()
            ->getRepository('AppBundle\Entity\User')->findOneBy(["npid"=>$id,"confirmationToken"=>$data['confirmationToken']]);
        if(is_null($user)){
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                    ["Confirmation token is expired"]
                ), 500)
            );
        }

        if(!$user->getLicenseToPracticeConfirmed()){
            $user->setLicenseToPracticeConfirmed(false);
        } else {
            $user->setLicenseToPracticeConfirmed(true);
        }
        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(UserPasswordType::class, $user, ['csrf_protection' => false]);

        $form->handleRequest($request);
        $form->submit($data);
        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors() as $key => $error) {
                if ($form->isRoot()) {
                    $errors['#'][] = $error->getMessage();
                } else {
                    $errors[] = $error->getMessage();
                }
            }
            if(count($errors) >0){
                return $this->handleView($this->view(
                    ApiResponseWrap::error(
                        ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                        $errors['#']
                    ), 500)
                );
            }
        }

        /**
         * @var $userManager UserManager
         */
        $userManager = $this->container->get('fos_user.user_manager');
        $user->setPlainPassword($data['plainPassword']);
        $user->setConfirmationToken(null);
        $userManager->updateUser($user);
        $em->flush();
        return $this->handleView(
            $this->view(ApiResponseWrap::success([
                'id' => $user->getId()
            ], $this->get('jms_serializer')))
        );
    }

    /**
     * @Post("/profile/{npid}/reset-password/request", name="_profileResetPasswordRequest")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function resetPasswordRequestAction(Request $request){
        $id = $request->get('npid');
        /**
         * @var $user User
         */
        $user = $this->getDoctrine()
            ->getRepository('AppBundle\Entity\User')->findOneBy(["npid"=>$id]);
        if(is_null($user)){
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                    ["User not found"],
                    $this->get('jms_serializer')
                ), 404)
            );
        }
        $tokenGenerator = $this->container->get('fos_user.util.token_generator');
        $user->setConfirmationToken($tokenGenerator->generateToken());
        $url = $this->get('router')->generate('fos_user_resetting_reset', array('token' => $user->getConfirmationToken()), true);
        return $this->handleView(
            $this->view(ApiResponseWrap::success([
                'confirmationUrl' => $url
            ], $this->get('jms_serializer')))
        );
    }

    /**
     * @Post("/logout", name="_profileLogout")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response 
     * @Security("has_role('ROLE_USER')")
     */
    public function logoutAction(Request $request)
    {
        $user = $this->getUser();

        $em = $this->getDoctrine()->getEntityManager();
        $repositories = [
            $rtRep = $this->getDoctrine()->getRepository('AppBundle:RefreshToken'),
            $atRep = $this->getDoctrine()->getRepository('AppBundle:AccessToken'),
        ];

        try {
            foreach ($repositories as $rep) {
                $tokens = $rep->findBy(['user' => $user]);
                foreach ($tokens as $token) {
                    $em->remove($token);
                }
            }
            $em->flush();
        } catch (\Exception $e) {
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_APPLICATION_EXCEPTION,
                    ApiResponseErrorWrap::MESSAGE_SOMETHING_WENT_WRONG,
                    $this->get('jms_serializer')
                ), 500)
            );
        }

        $this->get('security.token_storage')->setToken(null);
        $request->getSession()->invalidate();

        return $this->handleView(
            $this->view(ApiResponseWrap::success([], $this->get('jms_serializer')))
        );
    }

    /**
     * @Post("/profile/{npid}/reset-phone/request", name="_profileResetPhoneRequest")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function resetPhoneRequestAction(Request $request)
    {
        $id = $request->get('npid');
        $idPhone = $request->get('id');
        /**
         * @var $em EntityManager
         */
        $em = $this->getDoctrine()->getManager();
        /**
         * @var $user User
         */
        $user = $this->getDoctrine()
            ->getRepository('AppBundle\Entity\User')->findOneBy(["npid"=>$id]);
        if(is_null($user)){
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                    ["User not found"]
                ), 404)
            );
        }
        $tokenGenerator = $this->container->get('app.sms.service');

        $phone = $em->getRepository("AppBundle:Phone")->findOneBy(["user" => $user,"id" => $idPhone]);
        if(is_null($phone)){
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                    ["Phones not found"]
                ), 404)
            );
        }

        /**
         * @var $phone Phone
         */
        $phone->setSmsCode($tokenGenerator->generateSmsCode());

        $em->persist($phone);
        $em->flush();
        return $this->handleView(
            $this->view(ApiResponseWrap::success([
                'token' => $phone->getSmsCode()
            ], $this->get('jms_serializer')))
        );

    }

    /**
     * @Post("/profile/{npid}/reset-phone/reset", name="_profileResetPhone")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function resetPhoneAction(Request $request, User $user)
    {
        $id = $request->get('npid');

        $data = json_decode($request->getContent(), true);
        if(!isset($data['smsCode'])){
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                    ["Empty confirmation token"]
                ), 500)
            );
        }

        if(!isset($data['number'])){
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                    ["Empty phone number"]
                ), 500)
            );
        }
        /**
         * @var $phone Phone
         */
        $phone = $this->getDoctrine()
            ->getRepository('AppBundle\Entity\Phone')->findOneBy(["user"=>$user,"smsCode"=>$data['smsCode']]);
        if(is_null($phone)){
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                    ["Confirmation token is expired"]
                ), 500)
            );
        }

        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(UserPhoneType::class, $phone, ['csrf_protection' => false]);
        $form->handleRequest($request);
        $form->submit($data);

        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors() as $key => $error) {
                if ($form->isRoot()) {
                    $errors['#'][] = $error->getMessage();
                } else {
                    $errors[] = $error->getMessage();
                }
            }
            if(count($errors) >0){
                return $this->handleView($this->view(
                    ApiResponseWrap::error(
                        ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                        $errors['#']
                    ), 500)
                );
            }
        }

        /**
         * @var $phone Phone
         */
        $phone->setNumber($data['number']);
        $phone->setSmsCode(null);
        $em->persist($phone);
        $em->flush();
        return $this->handleView(
            $this->view(ApiResponseWrap::success([
                'id' => $phone->getId()
            ], $this->get('jms_serializer')))
        );
    }

    /**
     * @Post("/profile/{npid}/reset-email/request", name="_profileResetEmailRequest")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function resetEmailRequestAction(Request $request)
    {
        $id = $request->get('npid');
        $em = $this->getDoctrine()->getManager();
        /**
         * @var $user User
         */
        $user = $this->getDoctrine()
            ->getRepository('AppBundle:User')->findOneBy(["npid"=>$id]);
        if(is_null($user)){
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                    ["User not found."]
                ), 500)
            );
        }
        $tokenGenerator = $this->container->get('fos_user.util.token_generator');
        $user->setConfirmationToken($tokenGenerator->generateToken());
        $em->persist($user);
        $em->flush();
        return $this->handleView(
            $this->view(ApiResponseWrap::success([
                'token' => $user->getConfirmationToken()
            ], $this->get('jms_serializer')))
        );

    }

    /**
     * @Post("/profile/{npid}/reset-email/reset", name="_profileResetEmail")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function resetEmailAction(Request $request)
    {
        $id = $request->get('npid');

        $data = json_decode($request->getContent(), true);
        if(!isset($data['confirmationToken'])){
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                    ["Empty confirmation token"]
                ), 500)
            );
        }
        if(!isset($data['email'])){
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                    ["Empty email value"]
                ), 500)
            );
        }
        /**
         * @var $user User
         */
        $user = $this->getDoctrine()
            ->getRepository('AppBundle\Entity\User')->findOneBy(["npid"=>$id,"confirmationToken"=>$data['confirmationToken']]);
        if(is_null($user)){
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                    ["Confirmation token is expired"]
                ), 500)
            );
        }

        if(!$user->getLicenseToPracticeConfirmed()){
            $user->setLicenseToPracticeConfirmed(false);
        } else {
            $user->setLicenseToPracticeConfirmed(true);
        }
        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(UserEmailType::class, $user, ['csrf_protection' => false]);
        $form->handleRequest($request);
        $form->submit($data);

        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors() as $key => $error) {
                if ($form->isRoot()) {
                    $errors['#'][] = $error->getMessage();
                } else {
                    $errors[] = $error->getMessage();
                }
            }
            if(count($errors) >0){
                return $this->handleView($this->view(
                    ApiResponseWrap::error(
                        ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                        $errors['#']
                    ), 500)
                );
            }
        }

        try{
            /**
             * @var $userManager UserManager
             */
            $userManager = $this->get('fos_user.user_manager');
            $user->setEmail($data['email']);
            $user->setConfirmationToken(null);
            $userManager->updateCanonicalFields($user);
            $userManager->updateUser($user);
            $em->flush();
        } catch (Exception $e){
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                    ApiResponseErrorWrap::MESSAGE_SOMETHING_WENT_WRONG
                ), 500)
            );
        }

        return $this->handleView(
            $this->view(ApiResponseWrap::success([
                'id' => $user->getId()
            ], $this->get('jms_serializer')))
        );
    }

    /**
     * @Post("/profile/interests", name="_profileInterests")
     * @param int $id
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function postProfileInterestsAction(Request $request)
    {

        /**
         * @var $em EntityManager
         */
        $em = $this->getDoctrine()->getManager();
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        if(count($data) > 0){
            foreach($data as $key => $value){
                /**
                 * @var $interest  Interest
                 */
                $interest = $em
                    ->getRepository('AppBundle:Interest') ->findOneBy(["id"=>$key]);
                /**
                 * @var $interestUser  InterestUser
                 */
                if(is_null($interest)){
                    return $this->handleView($this->view(
                        ApiResponseWrap::error(
                            ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                            "Interest not found"
                        ), 404)
                    );
                }
                try{
                    $interestUser =  $em
                        ->getRepository('AppBundle:InterestUser')->findByUserAndInterest($user, $interest);
                } catch (Exception $e){
                    return $this->handleView($this->view(
                        ApiResponseWrap::error(
                            ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                            ApiResponseErrorWrap::MESSAGE_SOMETHING_WENT_WRONG
                        ), 500)
                    );
                }

                if(!$value && !is_null($interestUser)){
                    try{
                        $user->removeInterestUser($interestUser);
                    } catch (Exception $e){
                        return $this->handleView($this->view(
                            ApiResponseWrap::error(
                                ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                                ApiResponseErrorWrap::MESSAGE_SOMETHING_WENT_WRONG
                            ), 500)
                        );
                    }
                } else {
                    try{

                        /**
                         * @var $interestUser  InterestUser
                         */

                        $interestUser = new InterestUser();
                        $interestUser->setInterest($interest);
                        $interestUser->setUser($user);

                        if(!is_null($interestUser)){
                            $user->addInterestUser($interestUser);
                        }
                    } catch (Exception $e){
                        return $this->handleView($this->view(
                            ApiResponseWrap::error(
                                ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                                ApiResponseErrorWrap::MESSAGE_SOMETHING_WENT_WRONG
                            ), 500)
                        );
                    }

                }
            }
        }
        try {
            $em->persist($user);
            $em->flush();

        } catch (\Exception $e) {
            return $this->handleView($this->view(
                ApiResponseWrap::error(
                    ApiResponseErrorWrap::ERROR_APPLICATION_EXCEPTION,
                    [ApiResponseErrorWrap::MESSAGE_SOMETHING_WENT_WRONG]
                ), 500)
            );
        }
        return $this->handleView(
            $this->view(ApiResponseWrap::success(["id"=>$interest->getId()], $this->get('jms_serializer')))
        );
    }


    /**
     * @Post("/profile/tags", name="_profileTags")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function postProfileTagsAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $tag = new Tag();
        $data = json_decode($request->getContent(), true);
        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(TagType::class, $tag, ['csrf_protection' => false]);
        $form->handleRequest($request);
        $form->submit($data);

        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors() as $key => $error) {
                if ($form->isRoot()) {
                    $errors['#'][] = $error->getMessage();
                } else {
                    $errors[] = $error->getMessage();
                }
            }
            if(count($errors) >0){
                return $this->handleView($this->view(
                    ApiResponseWrap::error(
                        ApiResponseErrorWrap::ERROR_ENDPOINT_PARAMS,
                        $errors['#']
                    ), 500)
                );
            }
        }

        $em->persist($tag);
        $em->flush();
        return $this->handleView(
            $this->view(ApiResponseWrap::success([
                'id' => $tag->getId()
            ], $this->get('jms_serializer')))
        );

    }

}