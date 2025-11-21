<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use phpDocumentor\Reflection\DocBlock\Tag;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class AuthorController extends AbstractController
{
    /**
     * This method retrieves all authors with pagination.
     *
     * @param AuthorRepository $authorRepository The author repository interface
     * @param SerializerInterface $serializer The serializer interface
     * @return JsonResponse A JSON response containing the list of authors
     * @param Request $request The HTTP request object
     * @param TagAwareCacheInterface $cache The cache interface
     */
    #[Route('api/authors', name: 'authors', methods: ['GET'])]
    public function getAllAuthor(
        AuthorRepository $authorRepository,
        SerializerInterface $serializer,
        Request $request,
        TagAwareCacheInterface $cache
    ): JsonResponse {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);

        $idCache = "getAllAuthors-" . $page . "-" . $limit;

        $jsonAuthorList = $cache->get($idCache, function (ItemInterface $item) use ($authorRepository, $page, $limit, $serializer) {
            echo ("Fetching data from database...\n");
            $item->tag("authorsCache");
            $authorList = $authorRepository->findAllWithPagination($page, $limit);

            return $serializer->serialize($authorList, 'json', ['groups' => 'getBooks']);
        });

        return new JsonResponse($jsonAuthorList, Response::HTTP_OK, [], true);
    }

    /**
     * This method retrieves an author by his/her ID.
     *
     * @param Author $author The author entity
     * @param SerializerInterface $serializer The serializer interface
     * @return JsonResponse A JSON response containing the author data
     */
    #[Route('api/authors/{id}', name: 'getAuthor', methods: ['GET'])]
    public function getAuthor(Author $author, SerializerInterface $serializer): JsonResponse
    {
        $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getBooks']);

        return new JsonResponse($jsonAuthor, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * This method creates a new author. Only users with the ROLE_ADMIN role can access this resource.
     *
     * @param Request $request The HTTP request object
     * @param SerializerInterface $serializer The serializer interface
     * @param UrlGeneratorInterface $urlGenerator The URL generator interface
     * @param EntityManagerInterface $em The entity manager interface
     * @param ValidatorInterface $validator The validator interface
     * @param TagAwareCacheInterface $cache The cache interface
     * @return JsonResponse A JSON response containing the created author data
     */
    #[Route('api/authors', name: 'createAuthor', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: "You don't have access to this resource.")]
    public function createAuthor(
        Request $request,
        SerializerInterface $serializer,
        UrlGeneratorInterface $urlGenerator,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        TagAwareCacheInterface $cache
    ): JsonResponse {
        $author = $serializer->deserialize($request->getContent(), Author::class, 'json');

        // Verification des erreurs.        
        $errors = $validator->validate($author);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        // Invalidate cache
        $cache->invalidateTags(["authorsCache"]);

        // Save data in database
        $em->persist($author);
        $em->flush();

        $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getBooks']);
        $location = $urlGenerator->generate('detailAuthor', ['id' => $author->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonAuthor, Response::HTTP_CREATED, ['Location' => $location], true);
    }

    /**
     * This method deletes an author by his/her ID.
     *
     * @param Author $author The author entity
     * @param EntityManagerInterface $em The entity manager interface
     * @param TagAwareCacheInterface $cache The cache interface
     * @return JsonResponse A JSON response with no content
     */
    #[Route('api/authors/{id}', name: 'deleteAuthor', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: "You don't have access to this resource.")]
    public function deleteAuthor(Author $author, EntityManagerInterface $em, TagAwareCacheInterface $cache): JsonResponse
    {
        // Invalidate cache
        $cache->invalidateTags(["authorsCache"]);

        $em->remove($author);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * This method updates an existing author. Only users with the ROLE_ADMIN role can access this resource.
     *
     * @param Request $request The HTTP request object
     * @param Author $author The author entity to be updated
     * @param SerializerInterface $serializer The serializer interface
     * @param EntityManagerInterface $em The entity manager interface
     * @param ValidatorInterface $validator The validator interface
     * @param TagAwareCacheInterface $cache The cache interface
     * @return JsonResponse A JSON response with no content
     */
    #[Route('api/authors/{id}', name: 'updateAuthor', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: "You don't have access to this resource.")]
    public function updateAuthor(
        Request $request,
        Author $author,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        TagAwareCacheInterface $cache
    ): JsonResponse {
        // Retrieves all data sent as an array
        $updatedAuthor = $serializer->deserialize($request->getContent(), Author::class, 'json', ['object_to_populate' => $author]);
        // Sends data to the database after validation
        $errors = $validator->validate($updatedAuthor);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        // Invalidate cache
        $cache->invalidateTags(["authorsCache"]);

        // Sending data to the database
        $em->persist($updatedAuthor);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
