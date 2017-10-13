<?php

namespace Azine\EmailBundle\Controller;


use Azine\EmailBundle\Entity\SentEmail;
use Azine\EmailBundle\Form\SentEmailType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class AzineEmailController extends Controller
{

    /**
     *  Emails-Dashboard
     */
    public function emailsDashboardAction(Request $request)
    {
        $form = $this->createForm(new SentEmailType());

        $form->handleRequest($request);

        $repository = $this->getDoctrine()->getManager()->getRepository(SentEmail::class);
        $queryBuilder = $repository->search($form->getData());

        $paginator = $this->get('azine.email.bundle.pagination');

        $paginator->setTotalCount($repository->getTotalCount($queryBuilder));

        $queryBuilder->setMaxResults($paginator->getPageSize())
            ->setFirstResult($paginator->getOffset());

        $emails = $queryBuilder->getQuery()->getResult();
        $emailsArray = [];

        foreach ($emails as $key => $email){

            $emailsArray[$key]['recipients'] = implode(', ', $email->getRecipients());
            $emailsArray[$key]['template'] = $email->getTemplate();
            $emailsArray[$key]['sent'] = $email->getSent()->format('Y-m-d H:i:s');
            $emailsArray[$key]['variables'] = substr(json_encode($email->getVariables()), 0, 60);
            $emailsArray[$key]['token'] = $email->getToken();
        }

        return $this->render('AzineEmailBundle::emailsDashboard.html.twig', ['paginator' => $paginator,
            'emails' => $emailsArray, 'form' => $form->createView()]);
    }

    public function getEmailDetailsByTokenAction(Request $request, $token)
    {
        $email = $this->getDoctrine()->getManager()->getRepository(SentEmail::class)
            ->findOneByToken($token);

        if($email instanceof SentEmail){

            $recipients = implode(', ', $email->getRecipients());
            $variables = implode(', ', array_keys($email->getVariables()));

            return $this->render('AzineEmailBundle::sentEmailDetails.html.twig',
                ['email' => $email, 'recipients' => $recipients, 'variables' => $variables]);
        }

        $response = $this->render("AzineEmailBundle::emailNotFound.html.twig");
        $response->setStatusCode(404);

        return $response;
    }
}
