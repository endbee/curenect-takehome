<?php
// src/Controller/AuthController.php
namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class AuthController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET','POST'])]
    public function login(AuthenticationUtils $utils): Response
    {
        return $this->render('security/login.html.twig', [
            'last_username' => $utils->getLastUsername(),
            'error' => $utils->getLastAuthenticationError()
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // handled by Symfony
    }

    #[Route('/register', name: 'app_register', methods: ['GET','POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        UserRepository $users,
        EntityManagerInterface $em
    ): Response {
        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email'));
            $plain = (string) $request->request->get('password');

            if (!$email || !$plain) {
                $this->addFlash('error', 'Email and password are required.');
            } elseif ($users->findOneBy(['email' => strtolower($email)])) {
                $this->addFlash('error', 'Email already registered.');
            } else {
                $user = (new User())->setEmail($email);
                $user->setPassword($hasher->hashPassword($user, $plain));
                $em->persist($user);           // ← use injected EM
                $em->flush();
                $this->addFlash('success', 'Account created. You can log in now.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/register.html.twig');
    }
}
