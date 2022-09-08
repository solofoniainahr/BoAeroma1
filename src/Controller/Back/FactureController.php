<?php


namespace App\Controller\Back;

use DateTime;
use Stripe\Util\Set;
use App\Entity\Devis;
use App\Service\Mailer;
use App\Service\Useful;
use App\Entity\FactureAvoir;
use App\Entity\InvoiceOrder;
use App\Entity\BonDeCommande;
use App\Entity\FactureMaitre;
use App\Service\FactureExcel;
use App\Form\FactureAvoirType;
use App\Entity\FactureCommande;
use App\Service\InvoiceProcessing;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\FactureAvoirRepository;
use App\Repository\InvoiceOrderRepository;
use App\Repository\BonDeCommandeRepository;
use App\Repository\FactureMaitreRepository;
use App\Repository\MarqueBlancheRepository;
use App\Repository\FactureCommandeRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Exception\TokenNotFoundException;

class FactureController extends AbstractController
{
    public function liste(FactureCommandeRepository $repository, InvoiceProcessing $invoiceProcessing, Request $request, FactureMaitreRepository $factureMaitreRepository)
    {

        if ($request->isXmlHttpRequest()) {

            if ($request->request->get('state') && $request->request->get('state') == 'paid') {
                $content = $this->renderView('back/facture/tab_list.html.twig', [
                    'invoices' => $invoiceProcessing->getAllInvoices(true)
                ]);

                return new JsonResponse(['content' => $content]);
            }

            if ($request->request->get('state') && $request->request->get('state') == 'notpaid') {
                $content = $this->renderView('back/facture/tab_list.html.twig', [
                    'invoices' => $invoiceProcessing->getAllInvoices(null, true)
                ]);

                return new JsonResponse(['content' => $content]);
            }

            if ($request->request->get('state') && $request->request->get('state') == 'asc') {
                $content = $this->renderView('back/facture/tab_list.html.twig', [
                    'invoices' => $invoiceProcessing->getAllInvoices(null, null, true, null)
                ]);

                return new JsonResponse(['content' => $content]);
            }

            if ($request->request->get('state') && $request->request->get('state') == 'desc') {
                $content = $this->renderView('back/facture/tab_list.html.twig', [
                    'invoices' => $invoiceProcessing->getAllInvoices(null, null, null, true)
                ]);

                return new JsonResponse(['content' => $content]);
            }
        }

        return $this->render('back/facture/invoice_list.html.twig', [
            'invoices' => $invoiceProcessing->getAllInvoices(),
            'invoice' => true
        ]);
    }

    public function regenerer(FactureCommande $invoiceOrder, Useful $useful, Request $request, KernelInterface $kernel)
    {
        $path = "/pdf/order/Facture-{$invoiceOrder->getNumero()}.pdf";
        $finalPath = "{$kernel->getProjectDir()}/public$path";
       
        if(file_exists($finalPath))
        {
            unlink($finalPath);
        }

        $useful->regeneratePdf($invoiceOrder->getBonDeCommande(), $invoiceOrder);
        $this->addFlash('success', 'Facture régénérer avec succès');
        return $this->redirect($request->headers->get('referer'));
    }

    public function regenererExtravape(FactureMaitre $invoiceOrder, Useful $useful, Request $request, KernelInterface $kernel)
    {
        
        $path = "/pdf/order/Facture-{$invoiceOrder->getNumero()}.pdf";
        $finalPath = "{$kernel->getProjectDir()}/public$path";
       
        if(file_exists($finalPath))
        {
            unlink($finalPath);
        }

        $useful->regeneratePdf(null, null, $invoiceOrder);
        
        $this->addFlash('success', 'Facture régénérer avec succès');
        return $this->redirect($request->headers->get('referer'));
    }

    public function facturePrestashop(FactureCommande $invoiceOrder, Useful $useful, Request $request)
    {
       
        $useful->createInvoice($invoiceOrder->getBonDeCommande(), $invoiceOrder);
        $this->addFlash('success', 'Facture régénérer avec succès');
        return $this->redirect($request->headers->get('referer'));
    }

    public function factureExtravape(?BonDeCommande $bonCommande = null, KernelInterface $kernel, Useful $useful, InvoiceProcessing $invoiceProcessing, BonDeCommandeRepository $bonDeCommandeRepo, Request $request, EntityManagerInterface $em, SessionInterface $session)
    {

        if($request->isMethod('post'))
        {
            $data = $request->request->all();

            $data = array_unique($data);
            
            $bdc = [];

            if(count($data) > 0)
            {
                foreach($data as $d)
                {
                    $bonCommande = $bonDeCommandeRepo->find($d);
                    
                    if($bonCommande)
                    {
                        array_push($bdc, $bonCommande);
                    }
                }

                $invoice = $invoiceProcessing->extravapeInvoice($bdc);

                $path = "/pdf/order/Facture-{$invoice->getNumero()}.pdf";
                $finalPath = "{$kernel->getProjectDir()}/public$path";
               
                if(file_exists($finalPath))
                {
                    unlink($finalPath);
                }
                
                $useful->regeneratePdf(null, null, $invoice);

                //return $this->redirectToRoute("back_logistique_show_extravape_Invoice", ['id' => $invoice->getId() ]);
                return $this->redirectToRoute("back_modification_logistique", ['id' => $bonCommande->getId()]);
            }


        }

        if($bonCommande)
        {
            return $this->render('back/facture/creationfactureExtravape.html.twig', [
                'bonDeCommandes' => $bonDeCommandeRepo->fetchExtravapOrder(),
                'invoices' => true,
                'logistiques' => ($bonCommande)? true : null
            ]);
        }

        return $this->render('back/facture/creationfactureExtravape.html.twig', [
            'bonDeCommandes' => $bonDeCommandeRepo->fetchExtravapOrder(),
            'invoices' => true,
        ]);
    }

    public function showInvoiceInfo($id, FactureMaitreRepository $factureMaitreRepo, FactureCommandeRepository $factureCommandeRepo, $maitre, KernelInterface $kernel, Useful $useful, MarqueBlancheRepository $mbRepo, InvoiceProcessing $invoiceProcessing, BonDeCommandeRepository $bonDeCommandeRepo, Request $request, EntityManagerInterface $em, SessionInterface $session)
    {
        $client = null;

        if($maitre)
        {
            $invoiceOrder = $factureMaitreRepo->find($id);

            $bonDeCommandes = $invoiceOrder->getBonDeCommandes();
        
            if(count($bonDeCommandes) > 0)
            {

                $client = $bonDeCommandes[0]->getClient();

            }
        }
        else
        {
            $invoiceOrder = $factureCommandeRepo->find($id);

            $bonDeCommandes = $invoiceOrder->getBonDeCommande();

            if($bonDeCommandes)
            {
    
                $client = $bonDeCommandes->getClient();
            }
    
        }

        
        if ($invoiceOrder->getSoldeFinal()) {

            return $this->render('back/facture/showInvoiceInfo.html.twig', [
                'invoiceOrder' => $invoiceOrder,
                'client' => $client,
                'marqueBlanches' => $mbRepo->findBy(['client' => $client]),
                'invoices' => true,
                'maitre' => $maitre
            ]);
        }
      
    }

    public function escompteFacture( $id, $maitre, Request $request, InvoiceProcessing $invoiceProcessing, FactureCommandeRepository $factureCommandeRepo, FactureMaitreRepository $factureMaitreRepo, EntityManagerInterface $em)
    {
        
        if($request->isMethod('post'))
        {

            $facture = null;
            if($maitre)
            {
                $facture = $factureMaitreRepo->find($id);
            }
            else
            {
                $facture = $factureCommandeRepo->find($id);
            }
    
            $val = $request->request->get('escompte');

           
            if($val != "")
            {
                
                $tHt = $facture->getTotalHt();
    
                $result = $invoiceProcessing->calculEscompte($tHt, $val);
                
                $escompte = $result['escompte'];
    
                $facture->setEscompte($escompte);
    
                $netFinancier = $result['netFinancier'];
    
                $facture->setNetFinancier($netFinancier);
    
                $tva = $result['tva'];
    
                $facture->setTva($tva);
    
                $totalTtc = $result['totalTtc'];
    
                $facture->setTotalTtc($totalTtc);
                $facture->setPourcentageEscompte($val);

                $facture->setMontantAPayer($totalTtc);

                if( ! $maitre )
                {
                    $devis = $facture->getDevis();
                    $bonDeCommande = $facture->getBonDeCommande();
                    $escompteBon = $result['escompte'];
                    $dNetFinancier = $result['netFinancier'];

                    if($devis->getMontantFinal() and $devis->getMontantFinal()->getTotalHt() != $devis->getTotalHt())
                    {
                        $dHt = $devis->getMontantFinal()->getTotalHt();
                    }
                    else
                    {
                        $dHt = $devis->getTotalHt();
                    }

                    $devisResult = $invoiceProcessing->calculEscompte($dHt, $val);

                    $taxe = $devisResult['tva'];
                    $dTotalTtc = $devisResult['totalTtc'];

                    $devis->setTaxe($taxe);
                    $devis->setTotalTtc($dTotalTtc);

                    $bonDeCommande->setTaxe($taxe);
                    $bonDeCommande->setTotalTtc($dTotalTtc);
                    $bonDeCommande->setResteAPayer($dTotalTtc);
                    $bonDeCommande->setEscompte($escompteBon);
                    $bonDeCommande->setPourcentageEscompte($val);
                    $bonDeCommande->setNetFinancier($dNetFinancier);
                    
                }

                $em->flush();

                $this->addFlash('success', 'Escompte ajouté avec succè');

            }
            
            return $this->redirect($request->headers->get('referer'));

        }
        

    }

    public function ajoutAcompte(Request $request, FactureMaitre $factureMaitre, EntityManagerInterface $em)
    {
        if($request->isMethod('post'))
        {
            $acompte = $request->request->get("acompte_payer");
            $date = \DateTime::createFromFormat('d/m/Y', trim($request->request->get('date_paiement_acompte')));
            
            $prixTtc = floatval($factureMaitre->getTotalTtc()) - floatval($acompte);

            $factureMaitre->setAcompte($acompte);
            $factureMaitre->setTotalTtc($prixTtc);
            $factureMaitre->setTotalTtc($prixTtc);
            $factureMaitre->setDatePaiementAcompte($date);

            $em->flush();
            
            $this->addFlash('success', 'Acompte ajouté avec succès');

            return $this->redirect($request->headers->get('referer'));
            
        }
    }

    public function listeFactureExtravape(FactureMaitreRepository $factureMaitreRepo)
    {
        return $this->render('back/facture/listeFactureExtravape.html.twig', [
            'invoices' => $factureMaitreRepo->findBy([], ['id' => 'desc']),
            'groupeExtravape' => true
        ]);
    }

    public function infoExtravape(Request $request, BonDeCommandeRepository $bonDeCommandeRepo)
    {
        if($request->isXmlHttpRequest())
        {
            $bonDeCommande = $bonDeCommandeRepo->find($request->request->get('id'));
            
            return $this->json(['info' => $bonDeCommande->getResteAlivrer()], 200);
        }
    }


    public function listeFactureAvoir(FactureAvoirRepository $factureAvoirRepo)
    {
        
        return $this->render('back/facture/list_facture_avoir.html.twig', [
            'factures' => $factureAvoirRepo->findBy([], ['increment' => 'desc']),
            'avoir' => true,
            'invoices' => true
        ]);
    }

    public function factureAvoir(? FactureAvoir $avoir = null,  Request $request, Useful $useful, EntityManagerInterface $em, FactureAvoirRepository $factureAvoirRepo, FactureCommandeRepository $factureCommandeRepo, FactureMaitreRepository $factureMaitreRepo, InvoiceProcessing $invoiceProcessing)
    {
        $creation = false;
        if( !$avoir )
        {   
            $creation = true;
            $avoir = new FactureAvoir;
        }
        
        $form = $this->createForm(FactureAvoirType::class, $avoir);

        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $facture = $request->request->get('facture');
            
            $id = explode('_', $facture)[1];
          
            if(strpos($facture, 'facture') !== false)
            {
                $facture = $factureCommandeRepo->find($id);
            }
            else
            {
                $facture = $factureMaitreRepo->find($id);
            }

            if($facture instanceof FactureCommande)
            {
                $avoir->setFacture($facture);
            }
            else
            {
                $avoir->setFactureMaitre($facture);
            }

            if( !$avoir->getNumero() )
            {
                $incrementObj = $factureAvoirRepo->findBy([], ['increment' => 'desc'], 1);
    
                $increment = 0;
    
                if($incrementObj)
                {
                    $increment = $incrementObj[0]->getIncrement();
                }
               
                $increment++;
                
                $date = new DateTime();
    
                $numero = $date->format("Y")."-".str_pad($increment, 4, '0', STR_PAD_LEFT);
                
                $avoir->setNumero($numero);
                $avoir->setIncrement($increment);

                $useful->factureAvoir($avoir);

            }

            $em->persist($avoir);
            $em->flush();
            $this->addFlash('success', 'Création facture avoir effectuée avec succès');

            return $this->redirectToRoute('back_facture_avoir_list');
        }

        return $this->render('back/facture/creation_facture_avoir.html.twig', [
            'form' => $form->createView(),
            'invoices' => $invoiceProcessing->getAllInvoices(),
            'avoir' => $avoir,
            'creation' => $creation
        ]);
    }

    public function payerAvoir(FactureAvoir $avoir, EntityManagerInterface $em, Request $request, Mailer $mailer, Useful $useful, KernelInterface $kernel, InvoiceProcessing $invoiceProcessing)
    {
        if($request->isMethod('post'))
        {

            $date = \DateTimeImmutable::createFromFormat('d/m/Y', trim($request->request->get('date_paiement')));
            $montant = $request->request->get('amount_paid');
            
            if($montant)
            {
                $avoir->setPayer(true)
                        ->setDatePaiement($date)
                        ->setMontantPayer($montant);
                
                $this->addFlash(
                    'success',
                    'Montant ajouté avec succè'
                );
            }
            else
            {
                $avoir->setDatePaiement($date);

                $this->addFlash(
                    'success',
                    'Modification date de paiement éffectué avec succè'
                );
            }

            $em->flush($avoir);
           

            $useful->factureAvoir($avoir);

            if($montant)
            {
                $mailer->envoieAvoir($avoir);
            }
        }

        return $this->redirectToRoute('back_facture_avoir_list');
    }

    function ajaxFactureInfo(Request $request, FactureCommandeRepository $factureCommandeRepo, FactureMaitreRepository $factureMaitreRepo)
    {
        if($request->isXmlHttpRequest())
        {

            $facture = $request->request->get('id');

            $id = null;
            if($facture)
            {
                $id = explode('_', $facture)[1];

                if(strpos($facture, 'facture') !== false)
                {
                    $facture = $factureCommandeRepo->find($id);
                }
                else
                {
                    $facture = $factureMaitreRepo->find($id);
                }
                return $this->json($facture, 200, [], ['groups' => 'info']);
            }

            return $this->json(null, 200);

        }
    }

    public function downloadFactureAvoir(Request $request, FactureAvoir $facture, Useful $useful)
    {

        $filename = $useful->factureAvoir($facture);
        
        $path = "/pdf/order/$filename";
        return $this->redirect($path);

    }

     
    public function sendInvoice(FactureAvoir $invoice,Useful $useful, Mailer $mailer, Request $request, EntityManagerInterface $em)
    {
        if ($this->isCsrfTokenValid('send' . $invoice->getId(), $request->request->get('_token'))) {
            
            $mailer->envoieAvoir($invoice);

            $invoice->setExpedier(true);

            $em->flush();

            $this->addFlash("factureEnvoyer", "Facture avoir envoyé avec succèe");

            return $this->redirect($request->headers->get('referer'));
            
        } else {
            throw new TokenNotFoundException('Token invalide');
        }
    }


    public function factureExcel($id, Request $request, FactureExcel $factureExcel, FactureCommandeRepository $factureCommandeRepo, FactureMaitreRepository $factureMaitreRepo)
    {
        if($request->query->get('extravape'))
        {
            $facture = $factureMaitreRepo->find($id);
        }
        else
        {
            $facture = $factureCommandeRepo->find($id);
        }
        
        $reponse = $factureExcel->creer($facture);

        return $this->file($reponse['temp_file'], $reponse['fileName'], ResponseHeaderBag::DISPOSITION_INLINE);
       
    }
}
