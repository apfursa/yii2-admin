<?php

namespace wm\admin\models\ui\menu;

use Yii;

/**
 * This is the model class for table "menuitem".
 *
 * @property int $id
 * @property string $title
 * @property int $visible
 * @property int $position
 * @property int $userId
 * @property string $url
 * @property int $menuId
 * @property Menu $menu
 */
class MenuItem extends \wm\yii\db\ActiveRecord {

    /**
     * {@inheritdoc}
     */
    public static function tableName() {
        return 'admin_menu_item';
    }

    /**
     * {@inheritdoc}
     */
    public function rules() {
        return [
            [['title', 'visible', 'order', 'params', 'menuId', 'type'], 'required'],
            [['visible', 'menuId'], 'integer'],
            [['title', 'params', 'type'], 'string', 'max' => 255],
            [['menuId'], 'exist', 'skipOnError' => true, 'targetClass' => Menu::className(), 'targetAttribute' => ['menuId' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels() {
        return [
            'id' => 'id',
            'title' => 'Надпись ',
            'visible' => 'Скрытость',
            'order' => 'Позиция',
            'params' => 'Url',
            'type' => 'Тип',
            'menuId' => 'Menu ID',
        ];
    }

    /**
     * Gets query for [[Menu]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMenu() {
        return $this->hasOne(Menu::className(), ['id' => 'menuId']);
    }

    public function fields() {
        return [
            'id', 'title', 'visible', 'order', 'menuId', 'type',
            'params' => function () {
                $res = json_decode($this->params);
                return $res;
            },
        ];
    }

    public function getSchema() {
        $attributeLabels = $this->attributeLabels();
        unset($attributeLabels['menuId']);
        $attributeLabels['menu'] = 'меню';
        return $this->convertShema($attributeLabels);
    }

    public static function getItems($menuId, $userId) {
        if (!Menu::find()->where(['id' => $menuId])->one()) {
            Yii::error('$menuId не содержится в Базе данных');
            return [];
        }
        $models = self::find()->where(['menuId' => $menuId])->all();
        $res = [];
        foreach ($models as $value) {
            $settings = $value->getMenuItemPersonalSettings()->where(['userId' => $userId])->one();
            if ($settings) {
                $value->order = $settings->order;
                $value->visible = $settings->visible;
            }
            $res[] = $value;
        }
        return $res;
    }

    /**
     * Gets query for [[MenuItemPersonals]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMenuItemPersonalSettings() {
        return $this->hasMany(MenuItemPersonalSettings::className(), ['itemId' => 'id']);
    }

}