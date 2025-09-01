<?php

namespace App\Controller;

use App\Repository\TodoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Todo;
use App\FormType\TodoType;

final class TodosController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        TodoRepository $repo,
        EntityManagerInterface $em
    ): Response {
        $todo = new Todo();
        $form = $this->createForm(TodoType::class, $todo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // ensure new todos start as not done
            $todo->setDone(false);

            $em->persist($todo);
            $em->flush();

            $this->addFlash('success', 'Todo added!');
            return $this->redirectToRoute('index');
        }

        $todos = $repo->findBy([], ['id' => 'DESC']);

        return $this->render('index\index.html.twig', [
            'todos' => $todos,
            'form'  => $form->createView(),
        ]);
    }
}
