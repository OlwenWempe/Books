<?php

namespace App\EventSubscriber;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ExceptionSubscriber implements EventSubscriberInterface
{
    public function onExceptionEvent(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof HttpException) {
            $data = [
                'status' => $exception->getStatusCode(),
                'message' => $exception->getMessage(),
            ];
            $event->setResponse(new JsonResponse($data));
            // Le satut 500 n'est pas une exception HttpException d'ou l'intérêt de le traiter a part dans un boucle "else".
        } else {
            $data = [
                'status' => 500,
                'message' => $exception->getMessage(),
            ];
            $event->setResponse(new JsonResponse($data));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ExceptionEvent::class => 'onExceptionEvent',
        ];
    }
}