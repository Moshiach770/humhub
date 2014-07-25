<?php

/**
 * @package humhub.modules_core.admin.controllers
 * @since 0.5
 */
class UserController extends Controller {

    public $subLayout = "/_layout";

    /**
     * @return array action filters
     */
    public function filters() {
        return array(
            'accessControl', // perform access control for CRUD operations
        );
    }

    /**
     * Specifies the access control rules.
     * This method is used by the 'accessControl' filter.
     * @return array access control rules
     */
    public function accessRules() {
        return array(
            array('allow',
                'expression' => 'Yii::app()->user->isAdmin()'
            ),
            array('deny', // deny all users
                'users' => array('*'),
            ),
        );
    }

    /**
     * Returns a List of Users
     */
    public function actionIndex() {

        $model = new User('search');
        $model->super_admin = '';

        if (isset($_GET['User']))
            $model->attributes = $_GET['User'];


        $this->render('index', array(
            'model' => $model
        ));
    }

    /**
     * Edits a user
     *
     * @return type
     */
    public function actionEdit() {

        $_POST = Yii::app()->input->stripClean($_POST);

        $id = (int) Yii::app()->request->getQuery('id');
        $user = User::model()->resetScope()->notDeleted()->findByPk($id);

        if ($user == null)
            throw new CHttpException(404, Yii::t('AdminModule.controllers_UserController', 'User not found!'));

        $user->scenario = 'adminEdit';
        $user->profile->scenario = 'adminEdit';
        $profile = $user->profile;

        // Build Form Definition
        $definition = array();
        $definition['elements'] = array();

        $groupModels = Group::model()->findAll(array('order' => 'name'));

        // Add User Form
        $definition['elements']['User'] = array(
            'type' => 'form',
            'title' => 'Account',
            'elements' => array(
                'username' => array(
                    'type' => 'text',
                    'class' => 'form-control',
                    'maxlength' => 32,
                ),
                'email' => array(
                    'type' => 'text',
                    'class' => 'form-control',
                    'maxlength' => 32,
                ),
                'group_id' => array(
                    'type' => 'dropdownlist',
                    'class' => 'form-control',
                    'items' => CHtml::listData($groupModels, 'id', 'name'),
                ),
                'super_admin' => array(
                    'type' => 'checkbox',
                ),
                'status' => array(
                    'type' => 'dropdownlist',
                    'class' => 'form-control',
                    'items' => array(
                        User::STATUS_ENABLED => Yii::t('AdminModule.controllers_UserController', 'Enabled'),
                        User::STATUS_DISABLED => Yii::t('AdminModule.controllers_UserController', 'Disabled'),
                        User::STATUS_NEED_APPROVAL => Yii::t('AdminModule.controllers_UserController', 'Unapproved'),
                    ),
                ),
            ),
        );

        // Add Profile Form
        $definition['elements']['Profile'] = array_merge(array('type' => 'form'), $profile->getFormDefinition());

        // Get Form Definition
        $definition['buttons'] = array(
            'save' => array(
                'type' => 'submit',
                'label' => Yii::t('AdminModule.controllers_UserController', 'Save'),
                'class' => 'btn btn-primary',
            ),
            'become' => array(
                'type' => 'submit',
                'label' => Yii::t('AdminModule.controllers_UserController', 'Become this user'),
                'class' => 'btn btn-danger',
            ),
            'delete' => array(
                'type' => 'submit',
                'label' => Yii::t('AdminModule.controllers_UserController', 'Delete'),
                'class' => 'btn btn-danger',
            ),
        );

        $form = new HForm($definition);
        $form['User']->model = $user;
        $form['Profile']->model = $profile;

        if ($form->submitted('save') && $form->validate()) {
            $this->forcePostRequest();

            if ($form['User']->model->save()) {
                $form['Profile']->model->save();

                $this->redirect(Yii::app()->createUrl('admin/user'));
                return;
            }
        }

        // This feature is used primary for testing, maybe remove this in future
        if ($form->submitted('become')) {

            // Switch Identity
            Yii::import('application.modules_core.user.components.*');
            $newIdentity = new UserIdentity($user->username, '');
            $newIdentity->fakeAuthenticate();
            Yii::app()->user->login($newIdentity);

            $this->redirect(Yii::app()->createUrl('//'));
        }

        if ($form->submitted('delete')) {
            $this->redirect(Yii::app()->createUrl('admin/user/delete', array('id' => $user->id)));
        }

        $this->render('edit', array('form' => $form));
    }

    /**
     * Deletes a user permanently
     */
    public function actionDelete() {

        $id = (int) Yii::app()->request->getQuery('id');
        $doit = (int) Yii::app()->request->getQuery('doit');

        $user = User::model()->resetScope()->notDeleted()->findByPk($id);

        if ($user == null)
            throw new CHttpException(404, Yii::t('AdminModule.controllers_UserController', 'User not found!'));

        if ($doit == 2) {

            $this->forcePostRequest();

            foreach (SpaceMembership::GetUserSpaces() as $workspace) {
                if ($workspace->isSpaceOwner($user->id)) {
                    $workspace->addMember(Yii::app()->user->id);
                    $workspace->setSpaceOwner(Yii::app()->user->id);
                }
            }
            $user->delete();
            $this->redirect(Yii::app()->createUrl('admin/user'));
        }

        $this->render('delete', array('model' => $user));
    }

}