<?php

namespace App\Controller;

use App\Entity\Todo;
use App\FormType\TodoType;
use App\Service\TodoService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TodosController extends AbstractController
{
    public function __construct(private readonly TodoService $todoService) {}

    #[Route('/', name: 'index', methods: ['GET','POST'])]
    #[IsGranted('ROLE_USER')]
    public function index(Request $request, LoggerInterface $logger): Response
    {
        $todo = new Todo();
        $form = $this->createForm(TodoType::class, $todo)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->todoService->create($todo, $this->getUser());
            $this->addFlash('success', 'Todo added!');
            return $this->redirectToRoute('index');
        }

        return $this->render('web/index/index.html.twig', [
            'form'  => $form->createView(),
            'todos' => $this->todoService->listFor($this->getUser()),
        ]);
    }

    #[Route('/todo/{id}/toggle', name: 'todo_toggle', methods: ['POST'])]
    public function toggle(Todo $todo): Response
    {
        $this->todoService->toggle($todo);
        return $this->redirectToRoute('index');
    }

    #[Route('/todo/{id}/delete', name: 'todo_delete', methods: ['POST'])]
    public function delete(Todo $todo): Response
    {
        $this->todoService->delete($todo);
        $this->addFlash('success', 'Todo deleted.');
        return $this->redirectToRoute('index');
    }
}

