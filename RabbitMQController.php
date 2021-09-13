<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Order;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use WysylajTaniej\Component\Utils\Exception\ApiException;

class RabbitMQController extends Controller
{
    /**
     * @Route("/test/add_order_to_queue/{orderId}", name="add_order_to_queue")
     */
    public function indexAction(Request $request, $orderId){


        $order = $this->getDoctrine()->getManager()->getRepository('AppBundle:Order')->findOneBy(['id'=>$orderId]);
        $this->get('rabbit.service')->addOrderToQueue($order);

        return new Response('ok_added_to_queue');
    }

    /**
     * Metoda do ktorej przelewy 24 kieruj po platnosci
     *
     * @Route("/testOrder/{id}", name="orderSaveWaybills")
     */
    public function testOrderSaveWaybills(Request $request, $id){
        $em = $this->getDoctrine()->getManager();
        $order = $em->getRepository('AppBundle:Order')->findOneBy(['id' => $id]);

        $packages = $order->getPackages();

        foreach ($packages as $package) {
            if (is_null($package->getTrackingNumber())) {
                echo 'Waybill null' . PHP_EOL;

            }
        }

        $this->get('app.before.cm.service')->sendToCourierManager($order);

        return new Response('ok_try_catch_me');
    }

    /**
     * @Route("/test/sendMail", name="send_mail_test")
     */
    public function sendMailAction(Request $request, $email='kamil.hajduk@silksh.pl'){
        $message = \Swift_Message::newInstance(null)
            ->setSubject('Hello Email')
            ->setFrom('chain@s2h.pl')
            ->setTo($email)
            ->setBody(
                $this->renderView(
                // app/Resources/views/Emails/registration.html.twig
                    'Emails/waybills.html.twig',
                    array('name' => "List")
                ),
                'text/html'
            )
        ;

        $send = $this->get('mailer')->send($message);

        return new Response('mail');
    }


    /**
     * @Route("/test/check_orders_statuses/", name="check_orders_statuses")
     */
    public function checkAction(Request $request, $orderId){

        $kernel = $this->get('kernel');
        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput(array(
            'command' => 'tracking:fetch',
        ));
        // You can use NullOutput() if you don't need the output
        $output = new BufferedOutput();
        $application->run($input, $output);

        // return the output, don't use if you used NullOutput()
        $content = $output->fetch();

        // return new Response(""), if you used NullOutput()
        return new Response($content);
    }

    /**
     * @Route("/test/queue_start/", name="test_queue_start")
     */
    public function queueStart(Request $request, $orderId){
        $this->get('rabbit.execute.service')->execute();

        return new Response('ok');
    }

    /**
     * @Route("/test/old_database_connection/", name="test_old_database_connection")
     */
    public function oldDatabase(Request $request){
        $this->get('app.migration.service')->checkUserByEmail('lech.sawon@silksh.pl');

        return new Response('ok');

    }
    /**
     * @Route("/test/save_purchase_price/{order}", name="save_purchas_price")
     */
    public function savePurchasePrice(Request $request, Order $order){
        $this->get('app.purchase.price.service')->savePurchasePrice($order);

        return new Response('ok');

    }
}
