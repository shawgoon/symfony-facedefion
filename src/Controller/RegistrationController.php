<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\LoginAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Gumlet\ImageResize;

class RegistrationController extends AbstractController
{
    /**
     * @Route("/register", name="app_register")
     */
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, UserAuthenticatorInterface $userAuthenticator, LoginAuthenticator $authenticator, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // encode the plain password
            if($form->get('plainPassword')->getData() ===  $form->get('confirmPassword')->getData()){ 

                // comment obtenir l'url du dossier public ?
                $dirUpload = str_replace("\\","/",$this->getParameter('upload_directory'))."/";
                $dirAvatar = str_replace("\\","/",$this->getParameter('avatar_directory'))."/";

                // utiliser la fonction moveUploadFile de php
                move_uploaded_file($_FILES['registration_form']['tmp_name']['avatar'],
                $dirUpload.$_FILES['registration_form']['name']['avatar']);

                $user->setRoles(['ROLE_USER']);
                $user->setPassword(
                    $userPasswordHasher->hashPassword(
                        $user,
                        $form->get('plainPassword')->getData()
                        )
                    );
                    
                    // je redimensionne mon image, changer son extention et je lui intègre l'id d l'user
                    $entityManager->persist($user);
                    $entityManager->flush();
                    $image = new ImageResize($dirUpload.$_FILES['registration_form']['name']['avatar']);
                    $image->resizeToWidth(100);
                    $image->save($dirAvatar.$user->getId().'.webp', IMAGETYPE_WEBP);
                    // pour une autre miniature je recrée une 2è image :
                    $image2 = new ImageResize($dirUpload.$_FILES['registration_form']['name']['avatar']);
                    $image2->resizeToWidth(256);
                    $image2->save($dirAvatar.$user->getId().'x256.webp', IMAGETYPE_WEBP);
                    // après mes opérations, je supprime l'image originale
                    unlink($dirUpload.$_FILES['registration_form']['name']['avatar']);

                    // do anything else you need here, like send an email
                
                return $userAuthenticator->authenticateUser(
                    $user,
                    $authenticator,
                    $request
                );
            } else {
                return $this->redirectToRoute('app-register');
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
