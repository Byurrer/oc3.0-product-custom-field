<?php

class ControllerExtensionModuleProductMarkedfield extends Controller
{
	public function index()
	{
		//страницу настроек модуля оставим пустой, однако, можно почитать эту статью по поводу настроек модуля
		//https://byurrer.ru/ustanovka-modulya-stranicza-nastrojki-opencart.html
	}

	//########################################################################

	public function install()
	{
		$this->db->query("ALTER TABLE `".DB_PREFIX."product` ADD `marked` TINYINT UNSIGNED NOT NULL DEFAULT '0';");

		$this->load->model('setting/event');

		//событие "после загрузки формы товара" - для показа дополнительного поля товара (обязательна маркировка или нет)
		$this->model_setting_event->addEvent(
			'productmarkedfield', //код, в данном случае название модуля
			'admin/view/catalog/product_form/after', //событие 
			'extension/module/productmarkedfield/eventProductFormAfter' //обработчик
		);
		
		//событие "после редактирования товара" - для сохранения статуса маркировки
		$this->model_setting_event->addEvent(
			'productmarkedfield', 
			'admin/model/catalog/product/editProduct/after', 
			'extension/module/productmarkedfield/eventProductEditAfter'
		);
	}

	//************************************************************************

	public function uninstall()
	{
		//удаление из таблицы товаров поля маркировки
		$this->db->query("ALTER TABLE `".DB_PREFIX."product` DROP `marked`");

		//удаление обработчиков событий модуля
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('productmarkedfield');
	}

	//########################################################################

	//обработчик события "после загрузки формы товара", вставляет в форму данные о маркировке
	public function eventProductFormAfter(&$route, &$args, &$output)
	{
		@$this->load->library('simple_html_dom');
		
		//по умолчанию отключаем маркировку товара
		$isMarked = false;

		//если есть id товара - извлекаем его и проверяем это значение в БД
		if(preg_match("/product_id=(\d+)/", $args["action"], $aMatch))
		{
				$idProduct = $aMatch[1];
				$this->load->model('catalog/product');
				$aProduct = $this->model_catalog_product->getProduct($idProduct);
				$isMarked = $aProduct["marked"];
		}
		
		$html = str_get_html($output);
		$html->find('div#tab-data', 0)->innertext = 
		'<div class="form-group">
			<label class="col-sm-2 control-label">Маркирован</label>
			<div class="col-sm-10">
				<label class="radio-inline">
					<input type="radio" name="marked" value="1" '.($isMarked ? 'checked="checked"' : "").'>Да
				</label>
				<label class="radio-inline">
					<input type="radio" name="marked" value="0" '.(!$isMarked ? 'checked="checked"' : "").'>Нет
				</label>
			</div>
		</div>' . $html->find('div#tab-data', 0)->innertext;
		$output = $html->outertext;
	}

	//************************************************************************

	//обработчик события "после редактирования товара", обновляет информацию о маркировке товара на основании данных из формы
	public function eventProductEditAfter(&$route, &$args)
	{
		//в $args[0] лежит id товара
		$sSql = "UPDATE " . DB_PREFIX . "product SET marked = " . $this->db->escape($args[1]['marked']) . " WHERE product_id = '" . (int)$args[0] . "'";
		$this->db->query($sSql);
	}
}
