<?php

declare(strict_types=1);

namespace PhpMyAdmin\Controllers\Database\Structure;

use PhpMyAdmin\Bookmarks\BookmarkRepository;
use PhpMyAdmin\ConfigStorage\Relation;
use PhpMyAdmin\ConfigStorage\RelationCleanup;
use PhpMyAdmin\Controllers\AbstractController;
use PhpMyAdmin\Controllers\Database\StructureController;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\FlashMessages;
use PhpMyAdmin\Http\ServerRequest;
use PhpMyAdmin\Message;
use PhpMyAdmin\Operations;
use PhpMyAdmin\ResponseRenderer;
use PhpMyAdmin\Sql;
use PhpMyAdmin\Template;
use PhpMyAdmin\Transformations;
use PhpMyAdmin\Util;
use PhpMyAdmin\Utils\ForeignKey;

use function __;
use function count;

final class EmptyTableController extends AbstractController
{
    public function __construct(
        ResponseRenderer $response,
        Template $template,
        private DatabaseInterface $dbi,
        private Relation $relation,
        private RelationCleanup $relationCleanup,
        private Operations $operations,
        private FlashMessages $flash,
        private StructureController $structureController,
    ) {
        parent::__construct($response, $template);
    }

    public function __invoke(ServerRequest $request): void
    {
        $multBtn = $_POST['mult_btn'] ?? '';
        $selected = $_POST['selected'] ?? [];

        if ($multBtn !== __('Yes')) {
            $this->flash->addMessage('success', __('No change'));
            $this->redirect('/database/structure', ['db' => $GLOBALS['db']]);

            return;
        }

        $defaultFkCheckValue = ForeignKey::handleDisableCheckInit();

        $GLOBALS['sql_query'] = '';
        $selectedCount = count($selected);

        for ($i = 0; $i < $selectedCount; $i++) {
            $aQuery = 'TRUNCATE ';
            $aQuery .= Util::backquote($selected[$i]);

            $GLOBALS['sql_query'] .= $aQuery . ';' . "\n";
            $this->dbi->selectDb($GLOBALS['db']);
            $this->dbi->query($aQuery);
        }

        if (! empty($_REQUEST['pos'])) {
            $sql = new Sql(
                $this->dbi,
                $this->relation,
                $this->relationCleanup,
                $this->operations,
                new Transformations(),
                $this->template,
                new BookmarkRepository($this->dbi, $this->relation),
            );

            $_REQUEST['pos'] = $sql->calculatePosForLastPage($GLOBALS['db'], $GLOBALS['table'], $_REQUEST['pos']);
        }

        ForeignKey::handleDisableCheckCleanup($defaultFkCheckValue);

        $GLOBALS['message'] = Message::success();

        unset($_POST['mult_btn']);

        ($this->structureController)($request);
    }
}
