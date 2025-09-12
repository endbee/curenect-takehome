<?php

namespace App\Controller;

use App\Entity\Todo;
use App\FormType\TodoType;
use App\Repository\TodoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TodosController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET','POST'])]
    #[IsGranted('ROLE_USER')]
    public function index(Request $request, TodoRepository $repo, EntityManagerInterface $em): Response
    {
        $todo = new Todo();
        $form = $this->createForm(TodoType::class, $todo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $todo->setOwner($this->getUser());        // <-- ownership
            $todo->setDone(false);
            $em->persist($todo);
            $em->flush();
            $this->addFlash('success', 'Todo added!');
            return $this->redirectToRoute('index');
        }

        $todos = $repo->findByOwner($this->getUser());

        return $this->render('index/index.html.twig', [
            'todos' => $todos,
            'form'  => $form->createView(),
        ]);
    }

    #[Route('/todo/{id}/delete', name: 'todo_delete', methods: ['POST'])]
    public function delete(Todo $todo, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('DELETE', $todo);
        $em->remove($todo);
        $em->flush();
        $this->addFlash('success', 'Todo deleted.');
        return $this->redirectToRoute('index');
    }

    #[Route('/todo/{id}/toggle', name: 'todo_toggle', methods: ['POST'])]
    public function toggle(Todo $todo, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $todo);
        $todo->setDone(!$todo->isDone());
        $em->flush();
        return $this->redirectToRoute('index');
    }
}
