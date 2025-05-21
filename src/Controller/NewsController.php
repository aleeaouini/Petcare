<?php
// src/Controller/NewsController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Annotation\Route;

class NewsController extends AbstractController
{
    private $client;
    private $apiKey = '8f876c03c4ff4b1498d910a4091bf1b0';

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    #[Route('/news', name: 'app_news')]
    public function index(): Response
    {
        $response = $this->client->request('GET', 'https://newsapi.org/v2/everything', [
            'query' => [
                'q' => 'animals',
                'language' => 'fr',
                'pageSize' => 10,
                'apiKey' => $this->apiKey,
            ],
        ]);

        $data = $response->toArray();
        $articles = $data['articles'] ?? [];

        return $this->render('news/index.html.twig', [
            'articles' => $articles,
        ]);
    }
}
