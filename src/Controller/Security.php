<?php

namespace App\Controller;

use App\Core\Controller;
use App\Core\DB\DB;
use App\Core\Form;
use function App\Core\I18n\_m;
use App\Core\Log;
use App\Core\VisibilityScope;
use App\Entity\Follow;
use App\Entity\GSActor;
use App\Entity\LocalUser;
use App\Entity\Note;
use App\Security\Authenticator;
use app\Util\Common;
use App\Util\Exception\EmailTakenException;
use App\Util\Exception\NicknameTakenException;
use App\Util\Exception\ServerException;
use App\Util\Form\FormFields;
use App\Util\Nickname;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class Security extends Controller
{
    /**
     * Log a user in
     */
    public function login(AuthenticationUtils $authenticationUtils)
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('main_all');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $last_login_id = $authenticationUtils->getLastUsername();

        return [
            '_template'     => 'security/login.html.twig',
            'last_login_id' => $last_login_id,
            'error'         => $error,
            'notes_fn'      => fn ()      => Note::getAllNotes(VisibilityScope::$instance_scope),
        ];
    }

    /**
     * @codeCoverageIgnore
     */
    public function logout()
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * Register a user, making sure the nickname is not reserved and
     * possibly sending a confirmation email
     */
    public function register(Request $request,
                             GuardAuthenticatorHandler $guard_handler,
                             Authenticator $authenticator)
    {
        $form = Form::create([
            ['nickname', TextType::class, [
                'label'       => _m('Nickname'),
                'constraints' => [
                    new NotBlank(['message' => _m('Please enter a nickname')]),
                    new Length([
                        'min'        => Common::config('nickname', 'min_length'),
                        'minMessage' => _m(['Your nickname must be at least # characters long'], ['count' => Common::config('nickname', 'min_length')]),
                        'max'        => Nickname::MAX_LEN,
                        'maxMessage' => _m(['Your nickname must be at most # characters long'], ['count' => Nickname::MAX_LEN]), ]),
                ],
            ]],
            ['email', EmailType::class, [
                'label'       => _m('Email'),
                'constraints' => [ new NotBlank(['message' => _m('Please enter an email') ])],
            ]],
            FormFields::repeated_password(),
            ['register', SubmitType::class, ['label' => _m('Register')]],
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data             = $form->getData();
            $data['password'] = $form->get('password')->getData();

            // This will throw the appropriate errors, result ignored
            $user = LocalUser::findByNicknameOrEmail($data['nickname'], $data['email']);
            if ($user !== null) {
                // If we do find something, there's a duplicate
                if ($user->getNickname() == $data['nickname']) {
                    throw new NicknameTakenException;
                } else {
                    throw new EmailTakenException;
                }
            }

            $valid_nickname = Nickname::validate($data['nickname'], check_already_used: false);

            try {
                // This already checks if the nickname is being used
                $actor = GSActor::create(['nickname' => $valid_nickname]);
                $user  = LocalUser::create([
                    'nickname'       => $valid_nickname,
                    'outgoing_email' => $data['email'],
                    'incoming_email' => $data['email'],
                    'password'       => LocalUser::hashPassword($data['password']),
                ]);
                DB::persistWithSameId(
                    $actor,
                    $user,
                    // Self follow
                    fn (int $id) => DB::persist(Follow::create(['follower' => $id, 'followed' => $id]))
                );
                DB::flush();
                // @codeCoverageIgnoreStart
            } catch (UniqueConstraintViolationException $e) {
                // _something_ was duplicated, but since we already check if nickname is in use, we can't tell what went wrong
                $e = 'An error occurred while trying to register';
                Log::critical($e . " with nickname: '{$valid_nickname}' and email '{$data['email']}'");
                throw new ServerException(_m($e));
            }
            // @codeCoverageIgnoreEnd

            // generate a signed url and email it to the user
            if ($_ENV['APP_ENV'] !== 'dev' && Common::config('site', 'use_email')) {
                // @codeCoverageIgnoreStart
                Common::sendVerificationEmail();
            // @codeCoverageIgnoreEnd
            } else {
                $user->setIsEmailVerified(true);
            }

            return $guard_handler->authenticateUserAndHandleSuccess(
                $user,
                $request,
                $authenticator,
                'main' // firewall name in security.yaml
            );
        }

        return [
            '_template'         => 'security/register.html.twig',
            'registration_form' => $form->createView(),
            'notes_fn'          => fn ()          => Note::getAllNotes(VisibilityScope::$instance_scope),
        ];
    }
}
