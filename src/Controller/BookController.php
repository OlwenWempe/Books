<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\BookRepository;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class BookController extends AbstractController
{
    /**
     * This method retrieves a paginated list of books. It adds caching to optimize performance.
     *
     * @param BookRepository $bookRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/books', name: 'book', methods: ['GET'])]
    public function getBookList(
        BookRepository $bookRepository,
        SerializerInterface $serializer,
        Request $request,
        TagAwareCacheInterface $cache
    ): JsonResponse {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllbooks-" . $page . "-" . $limit;

        $jsonBookList = $cache->get($idCache, function (ItemInterface $item) use ($bookRepository, $page, $limit, $serializer) {
            echo ("Fetching data from database...\n");
            $item->tag("booksCache");
            $bookList = $bookRepository->findAllWithPagination($page, $limit);

            return $serializer->serialize($bookList, 'json', ['groups' => 'getBooks']);
        });

        return new JsonResponse($jsonBookList, Response::HTTP_OK, [], true);
    }

    /**
     * This method retrieves a book by its ID.
     * 
     * @param Book $book The book to retrieve
     * @param SerializerInterface $serializer The serializer interface
     * 
     */
    #[Route('/api/books/{id}', name: 'detailBook', methods: ['GET'])]
    public function getDetailBook(Book $book, SerializerInterface $serializer): JsonResponse
    {
        $jsonBook = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);

        return new JsonResponse($jsonBook, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * This method deletes a book according to its ID.
     * 
     * @param Book $book The book to delete
     * @param EntityManagerInterface $em The entity manager
     * @param TagAwareCacheInterface $cache The cache interface
     * @return JsonResponse A JSON response with no content
     */
    #[Route('/api/books/{id}', name: 'deleteBook', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: "You don't have access to this resource.")]
    public function deleteBook(Book $book, EntityManagerInterface $em, TagAwareCacheInterface $cache): JsonResponse
    {
        $cache->invalidateTags(["booksCache"]);
        $em->remove($book);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * This method creates a new book. Only users with the ROLE_ADMIN role can access this endpoint.
     *
     * @param Request $request The HTTP request object
     * @param SerializerInterface $serializer The serializer interface
     * @param EntityManagerInterface $em The entity manager interface
     * @param UrlGeneratorInterface $urlGenerator The URL generator interface
     * @param AuthorRepository $authorRepository The author repository interface
     * @param ValidatorInterface $validator The validator interface
     * @param TagAwareCacheInterface $cache The cache interface
     * @return JsonResponse
     */
    #[Route('api/books', name: "createBook", methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: "You don't have access to this resource.")]
    public function createBook(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $em,
        UrlGeneratorInterface $urlGenerator,
        AuthorRepository $authorRepository,
        ValidatorInterface $validator,
        TagAwareCacheInterface $cache
    ): JsonResponse {
        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');

        // Retrieves all data sent as an array
        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;

        // We look for the corresponding author and assign it to the book.
        // If "find" does not find the author, then null will be returned.

        $author = $authorRepository->find($idAuthor);
        $book->setAuthor($author);
        // Verification des erreurs.
        $errors = $validator->validate($book);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        // Deleting data in cache
        $cache->invalidateTags(["booksCache"]);

        // Sending data to the database
        $em->persist($book);
        $em->flush();

        $jsonBook = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);

        $location = $urlGenerator->generate('detailBook', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonBook, Response::HTTP_CREATED, ["location" => $location], true);
    }

    /**
     * This method updates an existing book. Only users with the ROLE_ADMIN role can access this endpoint.
     *
     * @param Request $request The HTTP request object
     * @param EntityManagerInterface $em The entity manager interface
     * @param AuthorRepository $authorRepository The author repository interface
     * @param Book $book The book entity to be updated
     * @param SerializerInterface $serializer The serializer interface
     * @param TagAwareCacheInterface $cache The cache interface
     * @return JsonResponse
     */
    #[Route('/api/books/{id}', name: 'updateBook', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: "You don't have access to this resource.")]
    public function updateBook(
        Request $request,
        EntityManagerInterface $em,
        AuthorRepository $authorRepository,
        Book $book,
        SerializerInterface $serializer,
        TagAwareCacheInterface $cache
    ): JsonResponse {
        $updatedBook = $serializer->deserialize($request->getContent(), Book::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $book]);

        // Retrieves all data sent as an array
        $content = $request->toArray();
        // We retrieve the author's id
        $idAuthor = $content['idAuthor'] ?? -1;
        // We look for the corresponding author and assign it to the book.
        // If "find" does not find the author, then null will be returned.
        $updatedBook->setAuthor($authorRepository->find($idAuthor));

        // Deleting data in cache
        $cache->invalidateTags(["booksCache"]);

        // Sending data to the database
        $em->persist($updatedBook);
        $em->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
