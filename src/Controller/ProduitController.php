<?php

namespace App\Controller;

use App\Entity\Categorie;
use App\Entity\Image;
use App\Entity\Produit;
use App\Entity\User;
use App\Form\ProduitType;
use App\Repository\CategorieRepository;
use App\Repository\ImageRepository;
use App\Repository\ProduitRepository;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
/**
 * @Route("/produit")
 */
class ProduitController extends AbstractController
{
    /**
     * @Route("/produit", name="produit")
     */
    public function index(): Response
    {
        return $this->render('produit/index.html.twig', [
            'controller_name' => 'ProduitController',
        ]);
    }


    /**
     * @Route("/afficherP",name="afficheProduit")
     */
    public function affiche(Request $request,PaginatorInterface $paginator){
        $repo=$this->getDoctrine()->getRepository(Categorie::class)->findAll();
        $produit=$this->getDoctrine()->getRepository(Produit::class)->findAll();


        $produits=$paginator->paginate(
            $produit,
            $request->query->getInt('page',1),
            4
        );


        return $this->render('produit/affiche.html.twig',[ 'repo'=>$repo, 'produits'=>$produits]);
    }

    /**
     * @Route("/afficherPM/{userid}",name="afficheProduitD")
     */
    public function afficheM(Request $request,PaginatorInterface $paginator, $userid){
        $repo=$this->getDoctrine()->getRepository(Categorie::class)->findAll();
        $produit=$this->getDoctrine()->getRepository(Produit::class)->findBy(array('userid' => $userid));
        $produits=$paginator->paginate(
            $produit,
            $request->query->getInt('page',1),
            4
        );

        return $this->render('produit/affichemes.html.twig',[ 'repo'=>$repo, 'produits'=>$produits]);
    }


    /**
     * @Route("/delete/{id}",name="deleteProduit")
     */
    public function delete($id,ProduitRepository $repo){

        $em=$this->getDoctrine()->getManager();
        $produit=$repo->find($id);
        $images=$produit->getImages();
        foreach ($images as $img){
            $em->remove($img);
        }
        $em->remove($produit);//delete
        $em->flush();//maj
        return $this->redirectToRoute('afficheProduit');
    }



    /**
     * @Route("/ajouterP/{userid}",name="AjouterProduit")
     */
    function AjoutP(Request $request,\Swift_Mailer $mailer, $userid , UserRepository $repository){

        $user=$repository->find($userid);
        $produit=new Produit();
        $produit->setUserid($userid);
        //je fais appel a un formulaire d??ja cr??e methode 1
        $form=$this->createForm(ProduitType::class,$produit);
        $form->add("Ajouter",SubmitType::class);
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
            //PARTIE IMAGES debut
            // On r??cup??re les images transmises
            $images = $form->get('images')->getData();

            // On boucle sur les images
            foreach($images as $image){
                // On g??n??re un nouveau nom de fichier
                $fichier = md5(uniqid()).'.'.$image->guessExtension();

                // On copie le fichier dans le dossier uploads
                $image->move(
                    $this->getParameter('images_directory'),
                    $fichier
                );

                // On cr??e l'image dans la base de donn??es
                $img = new Image();
                $img->setNom($fichier);
                $produit->addImage($img);
            }
            //PARTIE IMAGE fin
            //PARTIE MAILING debut
            $message = (new \Swift_Message('NOUVEAU PRODUIT'))
                ->setFrom('docdocpidev@gmail.com')
                ->setTo($user->getEmail())
                ->setBody(
                    $this->renderView(
                    // templates/emails/registration.html.twig
                        'emails/nouvprod.html.twig',['produit'=>$produit]), 'text/html')

                // you can remove the following code if you don't define a text version for your emails
                ->addPart(
                    $this->renderView(
                    // templates/emails/registration.txt.twig
                        'emails/nouvprod.txt.twig'
                    ),
                    'text/plain'
                )
            ;

            $mailer->send($message);
            //return $this->render(...);
            //PARTIE MAILING fin
            $em=$this->getDoctrine()->getManager();
            $em->persist($produit);
            $em->flush();
            return $this->redirectToRoute("afficheProduitD" ,array('userid'=>$userid));

        }
        return $this->render("produit/ajoutP.html.twig",['form'=>$form->createView()]);
    }




    /**
     * @Route("/update/{id}",name="updateProduit")
     */
    function update($id,ProduitRepository $repo,Request $request){
        $produit=$repo->find($id);
        //je fais appel a un formulaire d??ja cr??e methode 1
        $form=$this->createForm(ProduitType::class,$produit);
        $form->add("update",SubmitType::class);

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){

            // On r??cup??re les images transmises
            $images = $form->get('images')->getData();

            // On boucle sur les images
            foreach($images as $image){
                // On g??n??re un nouveau nom de fichier
                $fichier = md5(uniqid()).'.'.$image->guessExtension();

                // On copie le fichier dans le dossier uploads
                $image->move(
                    $this->getParameter('images_directory'),
                    $fichier
                );

                // On cr??e l'image dans la base de donn??es
                $img = new Image();
                $img->setNom($fichier);
                $produit->addImage($img);
            }

            $em=$this->getDoctrine()->getManager();
            $em->flush();
            return $this->redirectToRoute("afficheProduit");
        }

        return $this->render("produit/updateP.html.twig",['form'=>$form->createView()]);

    }


    /**
     * @Route("/searchProduitx ", name="searchProduitx")
     */
    public function searchProduitx(Request $request,NormalizerInterface $Normalizer)
    {
        $repository = $this->getDoctrine()->getRepository(Produit::class);
        $requestString=$request->get('searchValue');
        $produits = $repository->findProduitByRef($requestString);
        $jsonContent = $Normalizer->normalize($produits, 'json',['groups'=>'produits']);
        $retour=json_encode($jsonContent);
        return new Response($retour);
    }

    /**
     * @Route("/recherche",name="recherche")
     */
    function Recherche(ProduitRepository $repository,Request $request){
        $data=$request->get('search');
        $produit=$repository->findBy(['reference'=>$data]);
        return $this->render("produit/affiche.html.twig",
            ['produit'=>$produit]);
    }

    /**
     * @Route("/rech",name="rech")
     */
    public function rech(ProduitRepository $repository , Request $request)
    {

        if (isset($_POST['rech']))
        {
            $choix = $_POST['rech'];
            $produit=$repository->findProduitByCat($choix);
            return $this->render("produit/affiche.html.twig", ['produit'=>$produit]);
        }
        $produit=$this->getDoctrine()->getRepository(Produit::class)->findAll();
        return $this->render('produit/affiche.html.twig',['produit'=>$produit]);

    }

    /**
     * @Route ("/tri",name="triProduit")
     */
    public function tri(ProduitRepository $produits , Request $request ,PaginatorInterface $paginator)
    {

        $repo=$this->getDoctrine()->getRepository(Categorie::class)->findAll();
        if (isset($_POST['tri']))
        {
            $choix = $_POST['tri'];
            if ($choix=='PC')
            {
                $produit=$produits->OrderByPrixC();
                $produit=$paginator->paginate(
                    $produit,
                    $request->query->getInt('page',1),
                    4
                );
                return $this->render("produit/affiche.html.twig",['produits'=>$produit , 'repo'=>$repo]);
            }
            elseif ($choix=='PD')
            {
                $produit=$produits->OrderByPrixD();
                $produit=$paginator->paginate(
                    $produit,
                    $request->query->getInt('page',1),
                    4
                );
                return $this->render("produit/affiche.html.twig",['produits'=>$produit , 'repo'=>$repo]);
            }
            elseif ($choix=='RC')
            {
                $produit=$produits->OrderByRefC();
                $produit=$paginator->paginate(
                    $produit,
                    $request->query->getInt('page',1),
                    4
                );
                return $this->render("produit/affiche.html.twig",['produits'=>$produit , 'repo'=>$repo]);
            }
            elseif ($choix=='RD')
            {
                $produit=$produits->OrderByRefD();
                $produit=$paginator->paginate(
                    $produit,
                    $request->query->getInt('page',1),
                    4
                );
                return $this->render("produit/affiche.html.twig",['produits'=>$produit , 'repo'=>$repo]);
            }
            elseif ($choix=='QC')
            {
                $produit=$produits->OrderByQC();
                $produit=$paginator->paginate(
                    $produit,
                    $request->query->getInt('page',1),
                    4
                );
                return $this->render("produit/affiche.html.twig",['produits'=>$produit , 'repo'=>$repo]);
            }
            elseif ($choix=='QD')
            {
                $produit=$produits->OrderByQD();
                $produit=$paginator->paginate(
                    $produit,
                    $request->query->getInt('page',1),
                    4
                );
                return $this->render("produit/affiche.html.twig",['produits'=>$produit , 'repo'=>$repo]);
            }
        }

    }


    /**
     * @Route("/supprime/image/{id}", name="produit_delete_image", methods={"DELETE"})
     */
    public function deleteImage(Image $image, Request $request){
        $data = json_decode($request->getContent(), true);

        // On v??rifie si le token est valide
        if($this->isCsrfTokenValid('delete'.$image->getId(), $data['_token'])){
            // On r??cup??re le nom de l'image
            $nom = $image->getNom();
            // On supprime le fichier
            unlink($this->getParameter('images_directory').'/'.$nom);

            // On supprime l'entr??e de la base
            $em = $this->getDoctrine()->getManager();
            $em->remove($image);
            $em->flush();

            // On r??pond en json
            return new JsonResponse(['success' => 1]);
        }else{
            return new JsonResponse(['error' => 'Token Invalide'], 400);
        }
    }



    //Afficher prod back
    /**
     * @Route("/afficherCP",name="afficheCP")
     */
    public function afficheprodback(){
        $produit=$this->getDoctrine()->getRepository(Produit::class)->findAll();
        return $this->render('categorie/afficheCP.html.twig',['produit'=>$produit]);
    }
    /**
     * @Route("/deleteCP/{id}",name="deleteCP")
     */
    public function deleteprodback($id,ProduitRepository $repo){
        $em=$this->getDoctrine()->getManager();
        $produit=$repo->find($id);
        $images=$produit->getImages();
        foreach ($images as $img){
            $em->remove($img);
        }
        $em->remove($produit);//delete
        $em->flush();//maj
        return $this->redirectToRoute('afficheCP');
    }
    /**
     * @Route("/{id}", name="produit_show", methods={"GET"})
     */
    public function show(Produit $produit): Response
    {
        return $this->render('produit/show.html.twig', [
            'produit' => $produit,
        ]);
    }
    //Afficher prod back end


    /**
     * @Route("/ajouterPMobile/{email}",name="ajouterPMobile")
     * @param Request $request
     * @param NormalizerInterface $normalizer
     * @param CategorieRepository $repo
     * @return Response
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    function ajouterPMobile(Request $request,\Swift_Mailer $mailer,NormalizerInterface $normalizer, CategorieRepository $repo, $email){

        $em = $this->getDoctrine()->getManager();
        $produit = new Produit();
        $cat= $repo->find($request->get('categorie_id'));
        $produit->setCategorie($cat);
        $produit->setReference($request->get('reference'));
        $produit->setNom($request->get('nom'));
        $produit->setQuantite($request->get('quantite'));
        $produit->setDescription(($request->get('description')));
        $produit->setPrix($request->get('prix'));
        $produit->setUserid($request->get('userid'));

        $message = (new \Swift_Message('NOUVEAU PRODUIT'))
            ->setFrom('docdocpidev@gmail.com')
            ->setTo($request->get('email'))
            ->setBody(
                $this->renderView(
                // templates/emails/registration.html.twig
                    'emails/nouvprod.html.twig',['produit'=>$produit]), 'text/html')

            // you can remove the following code if you don't define a text version for your emails
            ->addPart(
                $this->renderView(
                // templates/emails/registration.txt.twig
                    'emails/nouvprod.txt.twig'
                ),
                'text/plain'
            )
        ;

        $mailer->send($message);


        $em->persist($produit);
        $em->flush();
        $jsonContent = $normalizer->normalize($produit, 'json', ['groups'=>'produits']);
        return new Response(json_encode($jsonContent));


    }

    /**
     * @Route("/rechercheMobile/{nom}",name="rechercheMobile")
     * @param $nom
     * @param NormalizerInterface $normalizer
     * @return Response
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function RechercheMobile($nom, NormalizerInterface $normalizer)
    {
        $repo = $this->getDoctrine()->getRepository(Produit::class);
        $produits=$repo->findBy(array('nom' => $nom));
        $json= $normalizer->normalize($produits, 'json',['groups'=>'produits']);
        return new Response(json_encode($json));
    }

    /**
     * @param NormalizerInterface $normalizer
     * @return Response
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     * @Route ("/afficherPMobile/{p}",name="afficherPMobile")
     */
    public function affichePMobile(NormalizerInterface $normalizer){
        $repo = $this->getDoctrine()->getRepository(Produit::class);
        $produits=$repo->findAll();
        $jsonContent = $normalizer->normalize($produits,'json',['groups'=>'produits']);
        return new Response(json_encode($jsonContent));

    }

    /**
     * @Route("/supprimerPMobile/{id}",name="supprimerPMobile")
     * @param NormalizerInterface $normalizer
     * @return Response
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    function supprimerPMobile(NormalizerInterface $normalizer,$id){

        $em = $this->getDoctrine()->getManager();
        $produit = $em->getRepository(Produit::class)->find($id);
        $em->remove($produit);
        $em->flush();
        $jsonContent = $normalizer->normalize($produit, 'json', ['groups'=>'produits']);
        return new Response(json_encode($jsonContent));

    }


    /**
     * @Route("/modifierPMobile/{id}",name="modifierPMobile")
     * @param NormalizerInterface $normalizer
     * @return Response
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    function modifierPMobile(Request $request,NormalizerInterface $normalizer,$id){

        $em = $this->getDoctrine()->getManager();
        $produit = $em->getRepository(Produit::class)->find($id);

        $produit->setReference($request->get('reference'));
        $produit->setNom($request->get('nom'));
        $produit->setQuantite($request->get('quantite'));
        $produit->setDescription($request->get('description'));
        $produit->setPrix($request->get('prix'));

        $em->flush();
        $jsonContent = $normalizer->normalize($produit, 'json', ['groups'=>'produits']);
        return new Response(json_encode($jsonContent));

    }

}
