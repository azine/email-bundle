<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Controller;

use Azine\EmailBundle\Entity\Repositories\SentEmailRepository;
use Azine\EmailBundle\Entity\SentEmail;
use Azine\EmailBundle\Form\SentEmailType;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Admin views for stored email web views.
 */
final class AzineEmailController
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly PaginatorInterface $paginator,
        private readonly FormFactoryInterface $formFactory,
        private readonly Environment $twig,
        private readonly int $webViewRetentionDays,
    ) {
    }

    public function emailsDashboardAction(Request $request): Response
    {
        $form = $this->formFactory->create(SentEmailType::class);
        $form->handleRequest($request);
        $searchParams = $form->getData() ?: [];

        /** @var SentEmailRepository $repository */
        $repository = $this->managerRegistry->getManager()->getRepository(SentEmail::class);
        $pagination = $this->paginator->paginate(
            $repository->search($searchParams),
            $request->query->getInt('page', 1),
        );

        return new Response($this->twig->render('@AzineEmail/emailsDashboard.html.twig', [
            'form' => $form->createView(),
            'pagination' => $pagination,
        ]));
    }

    public function emailDetailsByTokenAction(string $token): Response
    {
        /** @var SentEmailRepository $repository */
        $repository = $this->managerRegistry->getManager()->getRepository(SentEmail::class);
        $email = $repository->findOneBy(['token' => $token]);

        if ($email instanceof SentEmail) {
            return new Response($this->twig->render('@AzineEmail/sentEmailDetails.html.twig', [
                'email' => $email,
                'recipients' => implode(', ', $email->getRecipients() ?? []),
                'variables' => implode(', ', array_keys($email->getVariables() ?? [])),
            ]));
        }

        return new Response(
            $this->twig->render('@AzineEmail/Webview/mail.not.available.html.twig', [
                'days' => $this->webViewRetentionDays,
            ]),
            Response::HTTP_NOT_FOUND,
        );
    }
}
