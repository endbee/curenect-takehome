<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\RegistrationData;
use App\FormType\LoginFormType;
use App\FormType\RegistrationFormType;
use App\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class AuthController extends AbstractController
{
    public function __construct(private readonly AuthService $auth) {}

    #[Route('/login', name: 'app_login', methods: ['GET','POST'])]
    public function login(Request $request, AuthenticationUtils $utils): Response
    {
        $form = $this->createForm(LoginFormType::class, null, [
            'action' => $this->generateUrl('app_login'),
            'method' => 'POST',
        ]);
        $form->get('_username')->setData($utils->getLastUsername());

        return $this->render('web/security/login.html.twig', [
            'form'  => $form->createView(),
            'error' => $utils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // handled by Symfony firewall
    }

    #[Route('/register', name: 'app_register', methods: ['GET','POST'])]
    public function register(Request $request): Response
    {
        $data = new RegistrationData();
        $form = $this->createForm(RegistrationFormType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->auth->register($data->email, $data->plainPassword);

            if ($result->isSuccess()) {
                $this->addFlash('success', 'Account created. You can log in now.');
                return $this->redirectToRoute('app_login');
            }

            foreach ($result->getErrors() as $msg) {
                $this->addFlash('error', $msg);
            }
        }

        return $this->render('web/security/register.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}

