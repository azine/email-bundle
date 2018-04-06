<?php

namespace Azine\EmailBundle\Controller;

use Azine\EmailBundle\Entity\Repositories\SentEmailRepository;
use Azine\EmailBundle\Entity\SentEmail;
use Azine\EmailBundle\Form\SentEmailType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * This controller provides actions related to SentEmails stored in the database.
 */
class AzineEmailController extends Controller
{
    /**
     *  Displays an Emails-Dashboard with filters for each property of SentEmails entity and links to
     *  emailDetailsByToken & webView actions for each email.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function emailsDashboardAction(Request $request)
    {
        $form = $this->createForm(SentEmailType::class);
        $form->handleRequest($request);
        $searchParams = $form->getData();
        /** @var SentEmailRepository $repository */
        $repository = $this->getDoctrine()->getManager()->getRepository(SentEmail::class);
        $query = $repository->search($searchParams);
        $pagination = $this->get('knp_paginator')->paginate($query, $request->query->getInt('page', 1));

        return $this->render('AzineEmailBundle::emailsDashboard.html.twig',
            array('form' => $form->createView(), 'pagination' => $pagination));
    }

    /**
     * Displays an extended view of SentEmail entity searched by a token property.
     *
     * @param string $token
     *
     * @return Response
     */
    public function emailDetailsByTokenAction(Request $request, $token)
    {
        $email = $this->getDoctrine()->getManager()->getRepository(SentEmail::class)
            ->findOneByToken($token);

        if ($email instanceof SentEmail) {
            $recipients = implode(', ', $email->getRecipients());
            $variables = implode(', ', array_keys($email->getVariables()));

            return $this->render('AzineEmailBundle::sentEmailDetails.html.twig',
                array('email' => $email, 'recipients' => $recipients, 'variables' => $variables));
        }

        // the parameters-array is null => the email is not available in webView
        $days = $this->getParameter('azine_email_web_view_retention');
        $response = $this->render('AzineEmailBundle:Webview:mail.not.available.html.twig', array('days' => $days));
        $response->setStatusCode(404);

        return $response;
    }
}
