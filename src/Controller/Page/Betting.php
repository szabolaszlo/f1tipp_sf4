<?php

/**
 * Created by PhpStorm.
 * User: Carlos
 * Date: 2016. 12. 20.
 * Time: 21:18
 */

namespace App\Controller\Page;

use App\Builder\BetBuilder;
use App\Form\BettingType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\Bet;
use App\Entity\BetAttribute;
use App\Entity\Qualify;
use App\Entity\Race;
use App\Entity\Repository\Event;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use System\FormHelper\FormHelper;

/**
 * Class Betting
 * @package App\Controller\Page\Betting
 */
class Betting extends AbstractController
{
    /**
     * @var Qualify
     */
    protected $qualify;

    /**
     * @var array
     */
    protected $qualifyAttributes = array();

    /**
     * @var Race
     */
    protected $race;

    /**
     * @var array
     */
    protected $raceAttributes = array();

    /**
     * @var FormHelper
     */
    protected $formHelper;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var \App\Entity\Event
     */
    protected $event;

    /**
     * @var Bet
     */
    protected $qualifyBet;

    /**
     * @var Bet
     */
    protected $raceBet;

    /**
     * @var \DateTime
     */
    protected $now;

    /**
     * Betting constructor.
     * @param IRegistry $registry
     */
    public function __construct()
    {
        /*
                //User
                $this->data['user'] = $this->user = $this->registry->getUserAuth()->getLoggedUser();

                //UserToken
                $this->data['userToken'] = $this->registry->getUserAuth()->getActualToken();

                //Qualify
                $repository = $this->entityManager->getRepository('App\Entity\Qualify');
                $this->qualify = $repository->getNextEvent();

                //QualifyAttributes
                $this->qualifyAttributes = $this->registry->getRule()->getRuleType('qualify')->getAllAttribute();

                //QualifyBet
                $this->qualifyBet = $this->entityManager
                    ->getRepository('App\Entity\Bet')
                    ->findOneBy(array('user_id' => $this->user, 'event_id' => $this->qualify));

                //Race
                $repository = $this->entityManager->getRepository('App\Entity\Race');
                $this->race = $repository->getNextEvent();

                //RaceAttributes
                $this->raceAttributes = $this->registry->getRule()->getRuleType('race')->getAllAttribute();

                //RaceBet
                $this->raceBet = $this->entityManager
                    ->getRepository('App\Entity\Bet')
                    ->findOneBy(array('user_id' => $this->user, 'event_id' => $this->race));

                //FormHelper
                $this->formHelper = $this->registry->getFormHelper();

                //Now
                $this->now = new \DateTime();
                */
    }

    /**
     * @Route("/betting", name="betting", methods={"GET", "POST"})
     *
     * @param Request $request
     * @param BetBuilder $betBuilder
     * @return Response
     */
    public function indexAction(Request $request, BetBuilder $betBuilder)
    {
        $user = $this->getUser();

        $qualify = $this->getDoctrine()->getRepository('App:Qualify')->getNextEvent();
        $race = $this->getDoctrine()->getRepository('App:Race')->getNextEvent();

        $userBet = $this->getDoctrine()->getRepository('App:Bet')->getBetByUserAndEvent(
            $user,
            $qualify
        );

        $qualifyDefaultBet = $userBet ?? $betBuilder->buildForEvent($qualify);
        $qualifyDefaultBet->setUser($this->getUser());

        $raceDefaultBet = $betBuilder->buildForEvent($race);
        $raceDefaultBet->setUser($this->getUser());

        $events = [
            $this->getTimeDiff($qualify->getDateTime()) => [
                'event' => $qualify,
                'form' => $this->get('form.factory')->createNamed(
                    $qualifyDefaultBet->getEvent()->getType() . 'betting_form',
                    BettingType::class, $qualifyDefaultBet),
                //    'inTime' => (bool)($this->now < $this->qualify->getDateTime())
            ],
            $this->getTimeDiff($race->getDateTime()) => [
                'event' => $race,
                'form' => $this->get('form.factory')->createNamed(
                    $raceDefaultBet->getEvent()->getType() . 'betting_form',
                    BettingType::class, $raceDefaultBet),
            ]
        ];

        ksort($events);

        foreach ($events as &$event) {
            $form = $event['form'];
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $this->get("security.csrf.token_manager")->refreshToken("form_intention");
                // $form->getData() holds the submitted values
                // but, the original `$task` variable has also been updated
                $bet = $form->getData();

                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->persist($bet);
                $entityManager->flush();

                $this->addFlash('success', 'betting_success');

                return $this->redirectToRoute('betting');
            }
            $event['form'] = $form->createView();
        }

        return $this->render('controller/page/betting.html.twig', ['events' => $events]);
    }

    protected function getTimeDiff(\DateTime $time)
    {
        return abs(time() - $time->getTimestamp());
    }

    public function saveAction()
    {
        if (!$this->request->isPost()) {
            $this->redirectWithError();
        }

        if (!$this->validate()) {
            $this->redirectWithError();
        }

        $this->saveEntities();

        $this->session->set('success', $this->registry->getLanguage()->get('betting_success'));
        $this->registry->getServer()->redirect('page=betting/index');
    }

    protected function saveEntities()
    {
        $betAttributes = new ArrayCollection();
        $postedBetAttributes = $this->request->getPost('bet_attr', array());

        $bet = new Bet();
        $bet->setEvent($this->event);
        $bet->setUser($this->user);

        foreach ($postedBetAttributes as $key => $value) {
            $attribute = new BetAttribute();
            $attribute->setBet($bet);
            $attribute->setKey($key);
            $attribute->setValue($value);

            $betAttributes->add($attribute);
        }

        $bet->setAttributes($betAttributes);

        $this->entityManager->persist($bet);
        $this->entityManager->flush();
    }

    protected function redirectWithError()
    {
        $this->session->set('error', $this->registry->getLanguage()->get('betting_error'));
        $this->registry->getServer()->redirect('page=betting/index');
    }

    /**
     * @return bool
     */
    protected function validate()
    {
        $this->event = $this->entityManager->getRepository('App\Entity\Event')->find($this->request->getPost('event-id'));
        $this->user = $this->registry->getUserAuth()->getUserByToken($this->request->getPost('user-token'));

        $justInTime = (bool)($this->now < $this->event->getDateTime());

        if ($this->request->getPost('token', 'notEqual') != $this->session->get('BettingToken')) {
            $this->registry->getServer()->redirect('page=betting/index');
        }

        $this->session->remove('BettingToken');

        return (bool)($this->event && $this->user && $justInTime);
    }
}