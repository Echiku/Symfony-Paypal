<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{

    private $manager;
    private $product;

    public function __construct(EntityManagerInterface $manager, ProductRepository $product)
    {
        $this->manager=$manager;
        $this->product=$product;
    }


    //Load cart 
    //Affichage de la cart

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {   
        
       $products=$this->product->findAll();

       $count =count($products);

       $subtotal=0;
       $estimatedShipping=0;
       $total=0;

       foreach($products as $product)
       {
          $subtotal=$subtotal + $product->getPrice();
          $estimatedShipping=$estimatedShipping + $product->getEstimatedShipping();
       }

       $total = $subtotal + $estimatedShipping;

        return $this->render('home/cart.html.twig', [
            'products'=>$products,
            'count'=>$count,
            'subtotal'=>$subtotal,
            'shipping'=>$estimatedShipping,
            'total'=>$total
        ]);
    }

    //Add product
    //Ajouter un produit
    #[Route('/add', name:'add_product', methods:'POST')]
    public function store(Request $request)
    {
      $name=$request->request->get('name');
      $size=$request->request->get('size');
      $price=$request->request->get('price'); 
       // or we can just write $_RESUEST['price'] as you like// Cette écriture est aussi possible comme vous voulez.
      $qty=$request->request->get('qty');
      $estimatedShipping=$request->request->get('estimatedShipping');
      $image=$request->files->get('image');


      //Handle image// Traitement de l'image

      $filename=pathinfo($image->getClientOriginalName(),PATHINFO_FILENAME);
      $filename=str_replace("","_",$image);
      $filename=uniqid() .".".$image->getClientOriginalExtension();

      $image->move($this->getParameter('image_directory'),$filename);


      // New product // nouveau produit

      $product= new Product();

      $product->setName($name)
              ->setSize($size)
              ->setPrice($price)
              ->setQty($qty)
              ->setEstimatedShipping($estimatedShipping)
              ->setImage($filename);

     $this->manager->persist($product);
     $this->manager->flush();

    return new JsonResponse(
        [
            'status'=>true,
            'message'=>'Product added with success.'
        ]
    );

    }

    //bonus// Delete a product // Supprimer un produit

    #[Route('/delete/{id}', name:'delete_product')]
    public function delete($id)
    {
        $product= $this->product->find($id);
        
        $this->manager->remove($product);
        $this->manager->flush();

        return $this->redirectToRoute('app_home');
    }
}
