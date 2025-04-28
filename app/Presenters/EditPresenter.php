<?php
/*
Copyright Ilkka Forsblom.

This file is part of Nettepuoti.

Nettepuoti is free software: you can redistribute it and/or modify 
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the 
License, or (at your option) any later version.

Nettepuoti is distributed in the hope that it will be useful, 
but WITHOUT ANY WARRANTY; without even the implied warranty of 
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the 
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License 
along with Nettepuoti. If not, see <https://www.gnu.org/licenses/>. 
*/
namespace App\Presenters;

use Naja\Guide\Application\UI\Presenters\BasePresenter;
use Nette;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Nette\Caching\Cache;
use Nette\Http\IResponse;

final class EditPresenter extends BasePresenter
{
    private const MaxBrief = 150;
    private const MaxDescription = 1500;
    public function __construct(
        private Nette\Database\Explorer $database,
        private \App\Forms\FormFactory $factory,
        private Translator $translator,
        private Nette\Caching\Storage $storage,
    ) {
    }

    protected function startup()
    {
        parent::startup();
        if (!$this->getUser()->isAllowed('product')) {
            $this->error($this->translator->translate('g.edit.noRights'), IResponse::S403_Forbidden);
        }
        // $this->template->preview_link = $this->link('preview');
    }


    protected function createComponentProductForm(): Form
    {
        $form = $this->factory->create();
        $form->setTranslator($this->translator);
        $form->addText('name', 'g.edit.name')
            ->setRequired('g.edit.nameRequired');

        $form->addTextArea('brief', 'g.edit.brief')
            ->setRequired('g.edit.briefRequired')
            ->setMaxLength(self::MaxBrief);

        $form->addTextArea('description', 'g.edit.description')
            ->setOption("markdown", true)
            ->setMaxLength(self::MaxDescription)
            ->setRequired('g.edit.descriptionRequired');

        $form->addText('name_fi', 'g.edit.name_fi')
            ->setRequired('g.edit.name_fiRequired');
        $form->addTextArea('brief_fi', 'g.edit.brief_fi')
            ->setRequired('g.edit.brief_fiRequired')
            ->setMaxLength(self::MaxBrief);
        $form->addTextArea('description_fi', 'g.edit.description_fi')
            ->setRequired('g.edit.description_fiRequired')
            ->setMaxLength(self::MaxDescription)
            ->setOption("markdown", true);

        // ->setHtmlAttribute('hx-trigger', 'change, keyup delay:200ms changed');
        // $form->addFloat('price', 'g.edit.price')
        // ->setRequired('g.edit.priceRequired')
        // ->setHtmlAttribute('step', 0.01)
        // ->addRule($form::Float, 'Price must be a decimal number')
        // ->addRule($form::Min, 'g.edit.priceLimit', 0);
        $form->addSubmit('send', 'g.edit.save');

        // $form->addProtection();


        $form->onSuccess[] = $this->productFormSucceeded(...);

        return $form;
    }

    private function productFormSucceeded(array $data): void
    {
        $id = $this->getParameter('id');
        $redirect = 0;
        $dirty = ['home'];
        // $cache->clean([
        //     $cache::Tags => ["article/$articleId"],
        // ]);
        $user_id = $this->getUser()->getId();
        if ($id) {
            $product = $this->database
                ->table('products')
                ->get($id);
            if ($product->owner != $user_id) {
                $this->error($this->translator->translate('g.edit.noRights'), IResponse::S403_Forbidden);
            }
            $product->update($data);
            $dirty[] = "product/$id";
            $redirect = $id;
        } else {
            $data['owner'] = $user_id;
            $product = $this->database
                ->table('products')
                ->insert($data);
            $redirect = $this->database->getInsertId();
        }
        $this->flashMessage($this->translator->translate('g.edit.success'), 'alert-success');
        $cache = new Cache($this->storage, 'Nette.Templating.Cache');
        $cache->clean([$cache::Tags => $dirty]);
        $this->redirect('Product:show', $redirect);
    }

    public function renderEdit(int $id): void
    {
        $product = $this->database
            ->table('products')
            ->get($id);

        if (!$product) {
            $this->error($this->translator->translate('g.edit.notFound'));
        }
        $user_id = $this->getUser()->getId();
        if ($product->owner != $user_id) {
            $this->error($this->translator->translate('g.edit.notOwner'), IResponse::S403_Forbidden);
        }
        $this->getComponent('productForm')
            ->setDefaults($product->toArray());
    }

    public function renderPreview(string $description = '', string $description_fi = ''): void
    {
        $this->template->markdown = $description == '' ? $description_fi : $description;
    }
}