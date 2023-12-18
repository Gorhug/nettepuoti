<?php
namespace App\Presenters;

use Naja\Guide\Application\UI\Presenters\BasePresenter;
use Nette;
use Nette\Application\UI\Form;

final class EditPresenter extends BasePresenter
{
    public function __construct(
        private Nette\Database\Explorer $database,
    ) {
    }

    protected function createComponentProductForm(): Form
    {
        $form = new Form;
        $form->addText('name', 'Name:')
            ->setRequired('A name for the product is required');
        $form->addTextArea('description', 'Description:')
            ->setRequired('A description of the product is required');
        $form->addFloat('price', 'Price (€):')
            ->setRequired('A price for the product is required')
            // ->addRule($form::Float, 'Price must be a decimal number')
            ->addRule($form::Min, 'Price may not be negative', 0);
        $form->addSubmit('send', 'Save and publish');

        // $form->addProtection();


        $form->onSuccess[] = $this->productFormSucceeded(...);

        return $form;
    }

    private function productFormSucceeded(array $data): void
    {
        $productId = $this->getParameter('productId');

        if ($productId) {
            $product = $this->database
                ->table('products')
                ->get($productId);
            $product->update($data);
    
        } else {
            $product = $this->database
                ->table('posts')
                ->insert($data);
        }
        $this->flashMessage('Product was published', 'alert-success');
        $this->redirect('Product:show', $product->id);
    }

    public function renderEdit(int $productId): void
    {
        $product = $this->database
            ->table('products')
            ->get($productId);

        if (!$product) {
            $this->error('Product not found');
        }

        $this->getComponent('productForm')
            ->setDefaults($product->toArray());
    }


}