<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Tests\Controller;

use Azine\EmailBundle\Controller\AzineEmailController;
use Azine\EmailBundle\Entity\Repositories\SentEmailRepository;
use Azine\EmailBundle\Entity\SentEmail;
use Azine\EmailBundle\Form\SentEmailType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[AllowMockObjectsWithoutExpectations]
final class AzineEmailControllerTest extends TestCase
{
    public function testDashboardUsesInjectedFormRepositoryAndPaginator(): void
    {
        $request = new Request(['page' => '3']);
        $form = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('handleRequest')->with($request);
        $form->method('getData')->willReturn(['token' => 'stored']);
        $form->method('createView')->willReturn(new FormView());

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects(self::once())->method('create')->with(SentEmailType::class)->willReturn($form);

        $query = new \stdClass();
        $repository = $this->repository();
        $repository->expects(self::once())->method('search')->with(['token' => 'stored'])->willReturn($query);

        $pagination = $this->createMock(PaginationInterface::class);
        $paginator = $this->createMock(PaginatorInterface::class);
        $paginator->expects(self::once())->method('paginate')->with($query, 3)->willReturn($pagination);

        $controller = new AzineEmailController(
            $this->registry($repository),
            $paginator,
            $formFactory,
            new Environment(new ArrayLoader([
                '@AzineEmail/emailsDashboard.html.twig' => "{{ form is defined ? 'form' : 'missing' }}|{{ pagination is defined ? 'pagination' : 'missing' }}",
            ])),
            90,
        );

        $response = $controller->emailsDashboardAction($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('form|pagination', $response->getContent());
    }

    public function testDetailsRenderNullableStoredValuesSafely(): void
    {
        $email = (new SentEmail())
            ->setToken('stored-token')
            ->setRecipients(null)
            ->setVariables(null);

        $repository = $this->repository();
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['token' => 'stored-token'])
            ->willReturn($email);

        $controller = $this->controller($repository, [
            '@AzineEmail/sentEmailDetails.html.twig' => '{{ recipients }}|{{ variables }}|{{ email.token }}',
        ]);

        $response = $controller->emailDetailsByTokenAction('stored-token');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('||stored-token', $response->getContent());
    }

    public function testMissingDetailsReturnNotFoundResponse(): void
    {
        $repository = $this->repository();
        $repository->method('findOneBy')->willReturn(null);

        $controller = $this->controller($repository, [
            '@AzineEmail/Webview/mail.not.available.html.twig' => 'Unavailable after {{ days }} days',
        ]);

        $response = $controller->emailDetailsByTokenAction('missing');

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('Unavailable after 90 days', $response->getContent());
    }

    private function controller(SentEmailRepository $repository, array $templates): AzineEmailController
    {
        return new AzineEmailController(
            $this->registry($repository),
            $this->createMock(PaginatorInterface::class),
            $this->createMock(FormFactoryInterface::class),
            new Environment(new ArrayLoader($templates)),
            90,
        );
    }

    private function repository(): SentEmailRepository
    {
        return $this->getMockBuilder(SentEmailRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['search', 'findOneBy'])
            ->getMock();
    }

    private function registry(SentEmailRepository $repository): ManagerRegistry
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(SentEmail::class)->willReturn($repository);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($entityManager);

        return $registry;
    }
}
