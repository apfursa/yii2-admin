<?php

namespace wm\admin\controllers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yii;
use yii\filters\auth\CompositeAuth;
use yii\helpers\ArrayHelper;
use yii\web\Controller;

class ExcelController extends Controller
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        return [
            'corsFilter' => [
                'class' => \yii\filters\Cors::class,
                'cors' => [
                    // restrict access to
                    'Origin' => ['http://localhost:3000'],
                    // Allow only POST and PUT methods
                    'Access-Control-Request-Method' => ['POST', 'PUT', 'PATCH', 'DELETE', 'GET', 'OPTIONS'],
                    // Allow only headers 'X-Wsse'
                    'Access-Control-Request-Headers' => ['X-Wsse'],
                    // Allow credentials (cookies, authorization headers, etc.) to be exposed to the browser
                    'Access-Control-Allow-Credentials' => true,
                    // Allow OPTIONS caching
                    'Access-Control-Max-Age' => 3600,
                    // Allow the X-Pagination-Current-Page header to be exposed to the browser.
                    'Access-Control-Expose-Headers' => ['X-Pagination-Current-Page'],
                    'Access-Control-Allow-Origin' => ['*'],
                    'Access-Control-Allow-Headers' => ['*'],
                ],
            ],
            'authenticator' => [
                'class' => CompositeAuth::className(),
                'authMethods' => [
                    \wm\yii\filters\auth\HttpBearerAuth::className(),
                ],
            ]
        ];
    }

    public function beforeAction($action)
    {
        $this->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }

    public static function actionGetExcel()
    {
        $requestArr = Yii::$app->getRequest()->getBodyParams();
        $data = self::prepareDate(ArrayHelper::toArray($requestArr));
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()
            ->fromArray(
                $data,
                null,
                'A1'
            );
        $spreadsheet->getActiveSheet()
            ->getStyle(self::getHeadRange($data))
            ->applyFromArray(
                [
                    'font' => [
                        'bold' => true,
                    ],
                ]
            );
        $spreadsheet->getActiveSheet()
            ->getStyle(self::getRange($data))
            ->applyFromArray(
                [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => [
                                'rgb' => '000000']
                        ],
                    ],
                ]
            );
        self::setAutoWidthForColumns('A', self::getNameFromNumber(count(reset($data))), $spreadsheet);
        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $file = ob_get_contents();
        ob_clean();
        return $file;
    }

    /**
     * @param $data
     * @return string
     */
    public static function getHeadRange($data)
    {
        $result = 'A1:Z1';
        if (is_array($data)) {
            $count = count(reset($data));
            $result = 'A1:' . self::getNameFromNumber($count) . '1';
        }
        return $result;
    }

    /**
     * @param $data
     * @return string
     */
    public static function getRange($data)
    {
        $result = 'A1:Z1';
        if (is_array($data)) {
            $countRow = count($data);
            $count = count(reset($data));
            $result = 'A1:' . self::getNameFromNumber($count) . $countRow;
        }
        return $result;
    }

    /**
     * @param $num
     * @return string
     */
    public static function getNameFromNumber($num)
    {
        $numeric = ($num - 1) % 26;
        $letter = chr(65 + $numeric);
        $num2 = intval(($num - 1) / 26);
        if ($num2 > 0) {
            return self::getNameFromNumber($num2) . $letter;
        } else {
            return $letter;
        }
    }


    /**
     * @param $data
     * @return array
     * @throws \Exception
     */
    public static function prepareDate($data)
    {
        $result = [];
        if (ArrayHelper::getValue($data, 'schema')) {
            $columns = ArrayHelper::getColumn(ArrayHelper::getValue($data, 'schema'), 'code');
            $temp = [ArrayHelper::map(ArrayHelper::getValue($data, 'schema'), 'code', 'title')];
            $temp = array_merge($temp, self::getValues(ArrayHelper::getValue($data, 'grid'), $columns));
            $data = array_merge($temp, self::getValues(ArrayHelper::getValue($data, 'footer'), $columns));
        }
        foreach ($data as $row) {
            $tempRow = [];
            foreach ($row as $key => $value) {
                $tempValue = '';
                if (is_array($value)) {
                    switch (ArrayHelper::getValue($value, 'type')) {
                        case 'date':
                            if (ArrayHelper::getValue($value, 'format') == 'DD.MM.YYYY') {
                                $date = strtotime(ArrayHelper::getValue($value, 'title'));
                                $tempValue =date('d.m.Y', $date);
                            } elseif ('DD.MM.YYYY HH:mm:ss') {
                                $date = strtotime(ArrayHelper::getValue($value, 'title'));
                                $tempValue =date('d.m.Y h:i:s', $date);
                            } else {
                                $tempValue = ArrayHelper::getValue($value, 'title');
                            }
                            break;
                        default:
                            $tempValue = ArrayHelper::getValue($value, 'title');
                    }
                } else {
                    $tempValue = $value;
                }


                $tempRow[$key] = $tempValue;
            }
            $result[] = $tempRow;
        }
        return $result;
    }

    public static function getValues($data, $keys = ['id'])
    {
        $result = [];
        foreach ($data as $value) {
            $temp = [];
            foreach ($keys as $key) {
                $temp[$key] = ArrayHelper::getValue($value, $key);
            }

            $result[] = $temp;
        }
        return $result;
    }

    /**
     * Устанавливает стили для ячейки (ячеек)
     * @param string $cell Координаты ячейки (ячеек), пример: 'A1:A5'
     * @param array $styleArray
     * @param Spreadsheet $spreadsheet
     */
    public static function setStyleForExcell($cell, $styleArray, Spreadsheet $spreadsheet)
    {
        $spreadsheet->getActiveSheet()
            ->getStyle($cell)
            ->applyFromArray($styleArray);
    }

    /**
     * Устанавливает автоматическую ширину для столбцов
     * @param string $columnStart
     * @param string $columnEnd
     * @param Spreadsheet $spreadsheet
     */
    public static function setAutoWidthForColumns($columnStart, $columnEnd, Spreadsheet $spreadsheet)
    {
        foreach (range($columnStart, $columnEnd) as $columnID) {
            $spreadsheet->getActiveSheet()
                ->getColumnDimension($columnID)
                ->setAutoSize(true);
        }
    }
}
