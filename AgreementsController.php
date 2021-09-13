<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Agreement;
use AppBundle\Entity\AgreementUser;
use AppBundle\Entity\User;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use AppBundle\Component\Api\ApiResponseWrap;
use AppBundle\Component\Api\ApiResponseErrorWrap;
use BaseBundle\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;

class AgreementsController extends BaseController
{
    /**
     * @POST("/agreements/{npid}", name="_agreements")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editAgreementsAction(Request $request, User $user)
    {
        $post = $request->request;
        $em = $this->getDoctrine()->getEntityManager();

        foreach ($post as $key => $value){
            
            $agreement = $this->getDoctrine()->getRepository('AppBundle:Agreement')->findOneBy(['id' => $key]);
            
            if($agreement) {
                $agreementUser = $this->getDoctrine()->getRepository('AppBundle:AgreementUser')->findOneBy(['user' => $user, 'agreement' => $agreement]);
                if (true === $value && null === $agreementUser) {
                    
                    $agreementUser = new AgreementUser();
                    $agreementUser->setUser($user);
                    $agreementUser->setAgreement($agreement);

                    $em->persist($agreementUser);
                    $em->persist($user);
                    $em->persist($agreement);

                    
                } elseif (false === $value && null !== $agreementUser) {
                    $this->getDoctrine()->getRepository('AppBundle:AgreementUser')->removeHard($agreementUser->getId());
                }
            }
        }
        $em->flush();

        return $this->handleView(
            $this->view(ApiResponseWrap::success([], $this->get('jms_serializer')))
        );
    }

    /**
     * @GET("/agreement/{id}", name="_agreements_get_by_id")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getAgreementByIdAction($id,Request $request)
    {
        $agreements = $this->getDoctrine()->getRepository('AppBundle:Agreement')->findOneBy(['id' => $id]);
        /**
         * @var $agreement Agreement
         */
        return $this->handleView(
            $this->view(ApiResponseWrap::success($agreements, $this->get('jms_serializer')))
        );
    }

    /**
     * @GET("/agreements/default", name="_agreements_default")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getAgreementsDefaultAction(Request $request)
    {
        $agreements = $this->getDoctrine()->getRepository('AppBundle:Agreement')->findBy(['isDefaultAgreement' => true]);
        /**
         * @var $agreement Agreement
         */
        foreach($agreements as $agreement){
            $agreementData = [
                "description" => $agreement->getDescription(),
                "additional_information" => $agreement->getAdditionalInformation(),
                "displayed_for_occupations" => $agreement->getDisplayedForOccupations(),
                "id" => $agreement->getId()
            ];
        }
        return $this->handleView(
            $this->view(ApiResponseWrap::success($agreementData, $this->get('jms_serializer')))
        );
    }

    /**
     * @GET("/agreements", name="_agreements_all")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getAgreementstAction(Request $request)
    {
        $agreements = $this->getDoctrine()->getRepository('AppBundle:Agreement')->findAll();
        $agreementData = [];
        /**
         * @var $agreement Agreement
         */
        foreach($agreements as $agreement){
            $agreementData = [
                "description" => $agreement->getDescription(),
                "additional_information" => $agreement->getAdditionalInformation(),
                "displayed_for_occupations" => $agreement->getDisplayedForOccupations(),
                "id" => $agreement->getId()
            ];
         }
        return $this->handleView(
            $this->view(ApiResponseWrap::success($agreementData, $this->get('jms_serializer')))
        );
    }

    /**
     * @GET("/agreements/marketing", name="_agreements_marketing")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getAgreementsMarketingAction(Request $request)
    {
        $agreements = $this->getDoctrine()->getRepository('AppBundle:Agreement')->findBy(['isMarketingAgreement' => true]);

        return $this->handleView(
            $this->view(ApiResponseWrap::success($agreements, $this->get('jms_serializer')))
        );
    }
    /**
     * @GET("/sub-agreements/{id}", name="_agreements_sub")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getSubAgreementsAction(Request $request, Agreement $agreement )
    {
        $agreements = $agreement->getChildren();

        return $this->handleView(
            $this->view(ApiResponseWrap::success($agreements, $this->get('jms_serializer')))
        );
    }

}