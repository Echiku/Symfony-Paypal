<?php

namespace App\Controller;

use App\Entity\Payment;
use Omnipay\Omnipay;
use Doctrine\ORM\EntityManagerInterface;
use PhpParser\Node\Stmt\TryCatch;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class OperationController extends AbstractController
{

    private $gateway;
    private $manager;

    public function __construct(EntityManagerInterface $manager)
    {
        $this->gateway=Omnipay::create('PayPal_Rest');
        $this->gateway->setClientId($_ENV['PAYPAL_CLIENT_ID']);
        $this->gateway->setSecret($_ENV['PAYPAL_SECRET_KEY']);
        $this->gateway->setTestMode(true);

        $this->manager=$manager;
    }
    
    
    //Checkout and payement // Validation et paiement

    #[Route('/payment', name: 'app_payment', methods:'POST')]
    public function payment(Request $request): Response
    {

        //check form's token for security // Check si le token du formulaire est valide pour raison de sécurité. 
        $token=$request->request->get('token');

        if(!$this->isCsrfTokenValid('myform',$token))
        {
            return new Response('Operation not allowed', Response::HTTP_BAD_REQUEST, 
            ['content-type'=>'text/plain']);
        }

        try {
            $response= $this->gateway->purchase(array(
                'amount'=>$request->request->get('amount'),
                'currency'=>$_ENV['PAYPAL_CURRENCY'],
                'returnUrl'=>'https://127.0.0.1:8000/success',
                'cancelUrl'=>'https://127/0.0.1:8000/error'
            ))->send();

            if($response->isRedirect())
            {
                $response->redirect();
            }

            else
            {
                return $response->getMessage();
            }

        } catch (\Throwable $th) {
            return $th->getMessage();
        }
       

        return $this->render('operation/index.html.twig');
    }


    //Success op // si l'operation est un succès
    #[Route('/success', name:'app_success')]
    public function success(Request $request)
    {
         if($request->query->get('paymentId') && $request->query->get('PayerID'))
         {
            $operation = $this->gateway->completePurchase(array(
                'payer_id'=>$request->query->get('PayerID'),
                'transactionReference'=>$request->query->get('paymentId')
            ));

            $response=$operation->send();

            if($response->isSuccessful())
            {
                $arr=$response->getData();

                 $payement= new Payment();

                 $payement->setPaymentId($arr['id'])
                          ->setPayerId($arr['payer']['payer_info']['payer_id'])
                          ->setPayerEmail($arr['payer']['payer_info']['email'])
                          ->setAmount($arr['transactions'][0]['amount']['total'])
                          ->setCurrency($_ENV['PAYPAL_CURRENCY'])
                          ->setPurchasedAt(new \DateTime())
                          ->setPaymentStatus($arr['state']);

                $this->manager->persist($payement);
                $this->manager->flush();

                $name=$arr['transactions'][0]['item_list']['shipping_address']['recipient_name'];

                return $this->render('operation/success.html.twig',
                [
                    'message'=>$name.",the payment was successful ! Here your transaction Id: ".$arr['id']
                ]);
            }
            else
            {
                return $this->render('operation/success.html.twig',[
                    'message'=>$response->getMessage()
                ]);
            }
         }

         else
         {
            return $this->render('operation/success.html.twig',[
                'message'=>'Payment declined !'
            ]);
         }
    }


    #[Route('/error', name:'app_error')]
    public function error()
    {
       return $this->render('operation/error.html.twig',[
        'message'=>'User declined the payment !'
       ]);
    }
}
