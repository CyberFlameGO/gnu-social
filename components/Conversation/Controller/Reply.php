<?php

declare(strict_types = 1);
// {{{ License
// This file is part of GNU social - https://www.gnu.org/software/social
//
// GNU social is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// GNU social is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with GNU social.  If not, see <http://www.gnu.org/licenses/>.
// }}}

/**
 * @author    Hugo Sales <hugo@hsal.es>
 * @copyright 2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace Component\Conversation\Controller;

use App\Core\Controller\FeedController;
use App\Core\DB\DB;
use App\Core\Form;
use App\Util\Exception\DuplicateFoundException;
use App\Util\Exception\NoLoggedInUser;
use App\Util\Exception\ServerException;
use function App\Core\I18n\_m;
use App\Core\Log;
use App\Core\Router\Router;
use App\Entity\Actor;
use App\Entity\Note;
use App\Util\Common;
use App\Util\Exception\ClientException;
use App\Util\Exception\InvalidFormException;
use App\Util\Exception\NoSuchNoteException;
use App\Util\Exception\NotImplementedException;
use App\Util\Exception\RedirectException;
use App\Util\Form\FormFields;
use Component\Posting\Posting;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;

class Reply extends FeedController
{

    /**
     * Controller for the note reply non-JS page
     *
     * @param Request $request
     * @param int $id
     * @return array
     * @throws ClientException
     * @throws InvalidFormException
     * @throws NoSuchNoteException
     * @throws RedirectException
     * @throws DuplicateFoundException
     * @throws NoLoggedInUser
     * @throws ServerException
     */
    public function replyAddNote(Request $request, int $id)
    {
        $user     = Common::ensureLoggedIn();
        $actor_id = $user->getId();

        $note = Note::getWithPK($id);
        if (\is_null($note) || !$note->isVisibleTo($user)) {
            throw new NoSuchNoteException();
        }

        // TODO shouldn't this be the posting form?
        $form = Form::create([
            ['content', TextareaType::class, ['label' => _m('Reply'), 'label_attr' => ['class' => 'section-form-label'], 'help' => _m('Please input your reply.')]],
            FormFields::language($user->getActor(),
                context_actor: $note->getActor(),
                label: _m('Note language')),
            ['attachments', FileType::class, ['label' => ' ', 'multiple' => true, 'required' => false]],
            ['replyform', SubmitType::class, ['label' => _m('Submit')]],
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $data = $form->getData();
            if ($form->isValid()) {
                // Create a new note with the same content as the original
                $reply = Posting::storeLocalNote(
                    actor: Actor::getWithPK($actor_id),
                    content: $data['content'],
                    content_type: 'text/plain', // TODO
                    language: $data['language'],
                    attachments: $data['attachments'],
                );

                // Update DB
                DB::persist($reply);
                DB::flush();

                // Find the id of the note we just created
                $reply_id       = $reply->getId();
                $parent_id      = $note->getId();
                $resulting_note = Note::getWithPK($reply_id);
                $resulting_note->setReplyTo($parent_id);

                // Update DB one last time
                DB::merge($note);
                DB::flush();

                // Redirect user to where they came from
                // Prevent open redirect
                if (!\is_null($from = $this->string('from'))) {
                    if (Router::isAbsolute($from)) {
                        Log::warning("Actor {$actor_id} attempted to reply to a note and then get redirected to another host, or the URL was invalid ({$from})");
                        throw new ClientException(_m('Can not redirect to outside the website from here'), 400); // 400 Bad request (deceptive)
                    } else {
                        // TODO anchor on element id
                        throw new RedirectException($from);
                    }
                } else {
                    // If we don't have a URL to return to, go to the instance root
                    throw new RedirectException('root');
                }
            } else {
                throw new InvalidFormException();
            }
        }

        return [
            '_template' => 'reply/add_reply.html.twig',
            'note'      => $note,
            'add_reply' => $form->createView(),
        ];
    }

    public function replies(Request $request)
    {
        // TODO replies
        throw new NotImplementedException;
        $actor_id = Common::ensureLoggedIn()->getId();
        $notes    = DB::dql('select n from App\Entity\Note n '
                            . 'where n.reply_to is not null and n.actor_id = :id '
                            . 'order by n.created DESC', ['id' => $actor_id], );

        $notes_out = null;
        Event::handle('FormatNoteList', [$notes, &$notes_out]);

        return $this->process_feed([
            '_template'  => 'feeds/feed.html.twig',
            'notes'      => $notes_out,
            'page_title' => 'Replies feed',
        ]);
    }
}