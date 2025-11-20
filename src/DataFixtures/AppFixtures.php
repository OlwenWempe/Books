<?php

namespace App\DataFixtures;

use Faker;
use App\Entity\Book;
use App\Entity\User;
use App\Entity\Author;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $userPasswordHasher;

    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Création d'un user "normal
        $user = new User();
        $user->setEmail('user@bookapi.com');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, 'password'));
        $manager->persist($user);

        // Création d'un user "admin"
        $admin = new User();
        $admin->setEmail('admin@bookapi.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->userPasswordHasher->hashPassword($admin, 'adminpassword'));
        $manager->persist($admin);

        $faker = Faker\Factory::create('fr_FR');
        // Création des auteurs.

        $listAuthor = [];

        for ($i = 0; $i < 10; $i++) {

            // Création de l'auteur lui-même.

            $author = new Author();
            $author->setFirstName($faker->firstname());
            $author->setLastName($faker->lastname());
            $manager->persist($author);

            // On sauvegarde l'auteur créé dans un tableau.
            $listAuthor[] = $author;
        }

        // Création d'une vingtaine de livres ayant pour titre
        for ($i = 0; $i < 20; $i++) {

            $book = new Book;

            $book->setTitle($faker->realText(15));

            $book->setCoverText($faker->realText(200));

            $book->setAuthor($listAuthor[array_rand($listAuthor)]);

            $manager->persist($book);
        }

        $manager->flush();
    }
}