<?php
namespace Opencart\Admin\Model\Catalog;
class Category extends \Opencart\System\Engine\Model {
	public function addCategory($data) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "category` SET `parent_id` = '" . (int)$data['parent_id'] . "', `top` = '" . (isset($data['top']) ? (int)$data['top'] : 0) . "', `column` = '" . (int)$data['column'] . "', `sort_order` = '" . (int)$data['sort_order'] . "', `status` = '" . (int)$data['status'] . "', `date_modified` = NOW(), `date_added` = NOW()");

		$category_id = $this->db->getLastId();

		if (isset($data['image'])) {
			$this->db->query("UPDATE `" . DB_PREFIX . "category` SET `image` = '" . $this->db->escape((string)$data['image']) . "' WHERE `category_id` = '" . (int)$category_id . "'");
		}

		foreach ($data['category_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "category_description` SET `category_id` = '" . (int)$category_id . "', `language_id` = '" . (int)$language_id . "', `name` = '" . $this->db->escape($value['name']) . "', `description` = '" . $this->db->escape($value['description']) . "', `meta_title` = '" . $this->db->escape($value['meta_title']) . "', `meta_description` = '" . $this->db->escape($value['meta_description']) . "', `meta_keyword` = '" . $this->db->escape($value['meta_keyword']) . "'");
		}

		// MySQL Hierarchical Data Closure Table Pattern
		$level = 0;

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE `category_id` = '" . (int)$data['parent_id'] . "' ORDER BY `level` ASC");

		foreach ($query->rows as $result) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET `category_id` = '" . (int)$category_id . "', `path_id` = '" . (int)$result['path_id'] . "', `level` = '" . (int)$level . "'");

			$level++;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET `category_id` = '" . (int)$category_id . "', `path_id` = '" . (int)$category_id . "', `level` = '" . (int)$level. "'");

		if (isset($data['category_filter'])) {
			foreach ($data['category_filter'] as $filter_id) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "category_filter` SET `category_id` = '" . (int)$category_id . "', `filter_id` = '" . (int)$filter_id . "'");
			}
		}

		if (isset($data['category_store'])) {
			foreach ($data['category_store'] as $store_id) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "category_to_store` SET `category_id` = '" . (int)$category_id . "', `store_id` = '" . (int)$store_id . "'");
			}
		}

		$path = $this->getPath($category_id);

		foreach ($data['category_seo_url'] as $store_id => $language) {
			foreach ($language as $language_id => $keyword) {
				if ($keyword) {
					$this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET `store_id` = '" . (int)$store_id . "', `language_id` = '" . (int)$language_id . "', `key` = 'path', `value`='" . $this->db->escape($path) . "', `keyword` = '" . $this->db->escape($keyword) . "'");
				}
			}
		}

		// Set which layout to use with this category
		if (isset($data['category_layout'])) {
			foreach ($data['category_layout'] as $store_id => $layout_id) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "category_to_layout` SET `category_id` = '" . (int)$category_id . "', `store_id` = '" . (int)$store_id . "', `layout_id` = '" . (int)$layout_id . "'");
			}
		}

		return $category_id;
	}

	public function editCategory($category_id, $data) {
		$this->db->query("UPDATE `" . DB_PREFIX . "category` SET `parent_id` = '" . (int)$data['parent_id'] . "', `top` = '" . (isset($data['top']) ? (int)$data['top'] : 0) . "', `column` = '" . (int)$data['column'] . "', `sort_order` = '" . (int)$data['sort_order'] . "', `status` = '" . (int)$data['status'] . "', `date_modified` = NOW() WHERE `category_id` = '" . (int)$category_id . "'");

		if (isset($data['image'])) {
			$this->db->query("UPDATE `" . DB_PREFIX . "category` SET `image` = '" . $this->db->escape((string)$data['image']) . "' WHERE `category_id` = '" . (int)$category_id . "'");
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "category_description` WHERE category_id = '" . (int)$category_id . "'");

		foreach ($data['category_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "category_description` SET `category_id` = '" . (int)$category_id . "', `language_id` = '" . (int)$language_id . "', `name` = '" . $this->db->escape($value['name']) . "', `description` = '" . $this->db->escape($value['description']) . "', `meta_title` = '" . $this->db->escape($value['meta_title']) . "', `meta_description` = '" . $this->db->escape($value['meta_description']) . "', `meta_keyword` = '" . $this->db->escape($value['meta_keyword']) . "'");
		}

		// Old path
		$path_old = $this->getPath($category_id);

		// MySQL Hierarchical Data Closure Table Pattern
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE `path_id` = '" . (int)$category_id . "' ORDER BY `level` ASC");

		if ($query->rows) {
			foreach ($query->rows as $category_path) {
				// Delete the path below the current one
				$this->db->query("DELETE FROM `" . DB_PREFIX . "category_path` WHERE `category_id` = '" . (int)$category_path['category_id'] . "' AND `level` < '" . (int)$category_path['level'] . "'");

				$path = [];

				// Get the nodes new parents
				$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE `category_id` = '" . (int)$data['parent_id'] . "' ORDER BY `level` ASC");

				foreach ($query->rows as $result) {
					$path[] = $result['path_id'];
				}

				// Get whats left of the nodes current path
				$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE `category_id` = '" . (int)$category_path['category_id'] . "' ORDER BY `level` ASC");

				foreach ($query->rows as $result) {
					$path[] = $result['path_id'];
				}

				// Combine the paths with a new level
				$level = 0;

				foreach ($path as $path_id) {
					$this->db->query("REPLACE INTO `" . DB_PREFIX . "category_path` SET `category_id` = '" . (int)$category_path['category_id'] . "', `path_id` = '" . (int)$path_id . "', `level` = '" . (int)$level . "'");

					$level++;
				}
			}
		} else {
			// Delete the path below the current one
			$this->db->query("DELETE FROM `" . DB_PREFIX . "category_path` WHERE `category_id` = '" . (int)$category_id . "'");

			// Fix for records with no paths
			$level = 0;

			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE `category_id` = '" . (int)$data['parent_id'] . "' ORDER BY `level` ASC");

			foreach ($query->rows as $result) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET `category_id` = '" . (int)$category_id . "', `path_id` = '" . (int)$result['path_id'] . "', `level` = '" . (int)$level . "'");

				$level++;
			}

			$this->db->query("REPLACE INTO `" . DB_PREFIX . "category_path` SET `category_id` = '" . (int)$category_id . "', `path_id` = '" . (int)$category_id . "', `level` = '" . (int)$level . "'");
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "category_filter` WHERE `category_id` = '" . (int)$category_id . "'");

		if (isset($data['category_filter'])) {
			foreach ($data['category_filter'] as $filter_id) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "category_filter` SET `category_id` = '" . (int)$category_id . "', `filter_id` = '" . (int)$filter_id . "'");
			}
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "category_to_store` WHERE `category_id` = '" . (int)$category_id . "'");

		if (isset($data['category_store'])) {
			foreach ($data['category_store'] as $store_id) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "category_to_store` SET `category_id` = '" . (int)$category_id . "', `store_id` = '" . (int)$store_id . "'");
			}
		}

		// SEO URL
		$path = $this->getPath($category_id);

		$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE `key` = 'path' AND `value` = '" . $this->db->escape($path_old) . "'");

		if (isset($data['category_seo_url'])) {
			foreach ($data['category_seo_url'] as $store_id => $language) {
				foreach ($language as $language_id => $keyword) {
					if ($keyword) {
						$this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET `store_id` = '" . (int)$store_id . "', `language_id` = '" . (int)$language_id . "', `key` = 'path', `value` = '" . $this->db->escape($path) . "', `keyword` = '" . $this->db->escape($keyword) . "'");
					}
				}
			}
		}

		// Update all the previous paths to the new one
		$this->db->query("UPDATE `" . DB_PREFIX . "seo_url` SET `value` = REPLACE(`value`, '" . $this->db->escape($path_old) . "', '" . $this->db->escape($path) . "') WHERE `key` = 'path' AND `value` LIKE '" . $this->db->escape($path_old) . "\_%'");

		// Layouts
		$this->db->query("DELETE FROM `" . DB_PREFIX . "category_to_layout` WHERE `category_id` = '" . (int)$category_id . "'");

		if (isset($data['category_layout'])) {
			foreach ($data['category_layout'] as $store_id => $layout_id) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "category_to_layout` SET `category_id` = '" . (int)$category_id . "', `store_id` = '" . (int)$store_id . "', `layout_id` = '" . (int)$layout_id . "'");
			}
		}
	}

	public function deleteCategory($category_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "category_path` WHERE `category_id` = '" . (int)$category_id . "'");

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE `path_id` = '" . (int)$category_id . "'");

		foreach ($query->rows as $result) {
			$this->deleteCategory($result['category_id']);
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "category` WHERE `category_id` = '" . (int)$category_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "category_description` WHERE `category_id` = '" . (int)$category_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "category_filter` WHERE `category_id` = '" . (int)$category_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "category_to_store` WHERE `category_id` = '" . (int)$category_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "category_to_layout` WHERE `category_id` = '" . (int)$category_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "product_to_category` WHERE `category_id` = '" . (int)$category_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "coupon_category` WHERE `category_id` = '" . (int)$category_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE `key` = 'path' AND `value` = '" . $this->db->escape($this->getPath($category_id)) . "'");
	}

	public function repairCategories($parent_id = 0) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category` WHERE `parent_id` = '" . (int)$parent_id . "'");

		foreach ($query->rows as $category) {
			// Delete the path below the current one
			$this->db->query("DELETE FROM `" . DB_PREFIX . "category_path` WHERE `category_id` = '" . (int)$category['category_id'] . "'");

			// Fix for records with no paths
			$level = 0;

			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE `category_id` = '" . (int)$parent_id . "' ORDER BY `level` ASC");

			foreach ($query->rows as $result) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET `category_id` = '" . (int)$category['category_id'] . "', `path_id` = '" . (int)$result['path_id'] . "', `level` = '" . (int)$level . "'");

				$level++;
			}

			$this->db->query("REPLACE INTO `" . DB_PREFIX . "category_path` SET `category_id` = '" . (int)$category['category_id'] . "', `path_id` = '" . (int)$category['category_id'] . "', `level` = '" . (int)$level . "'");

			$this->repairCategories($category['category_id']);
		}
	}

	public function getCategory($category_id) {
		$query = $this->db->query("SELECT DISTINCT *, (SELECT GROUP_CONCAT(cd1.`name` ORDER BY `level` SEPARATOR '&nbsp;&nbsp;&gt;&nbsp;&nbsp;') FROM `" . DB_PREFIX . "category_path` cp LEFT JOIN `" . DB_PREFIX . "category_description` cd1 ON (cp.`path_id` = cd1.`category_id` AND cp.`category_id` != cp.`path_id`) WHERE cp.`category_id` = c.`category_id` AND cd1.`language_id` = '" . (int)$this->config->get('config_language_id') . "' GROUP BY cp.`category_id`) AS path FROM `" . DB_PREFIX . "category` c LEFT JOIN `" . DB_PREFIX . "category_description` cd2 ON (c.`category_id` = cd2.`category_id`) WHERE c.`category_id` = '" . (int)$category_id . "' AND cd2.`language_id` = '" . (int)$this->config->get('config_language_id') . "'");

		return $query->row;
	}

	public function getPath($category_id) {
		return implode('_', array_column($this->getPaths($category_id), 'path_id'));
	}

	public function getCategories($data = []) {
		$sql = "SELECT cp.`category_id` AS `category_id`, GROUP_CONCAT(cd1.`name` ORDER BY cp.`level` SEPARATOR '&nbsp;&nbsp;&gt;&nbsp;&nbsp;') AS `name`, c1.`parent_id`, c1.`sort_order` FROM `" . DB_PREFIX . "category_path` cp LEFT JOIN `" . DB_PREFIX . "category` c1 ON (cp.`category_id` = c1.`category_id`) LEFT JOIN `" . DB_PREFIX . "category` c2 ON (cp.`path_id` = c2.`category_id`) LEFT JOIN `" . DB_PREFIX . "category_description` cd1 ON (cp.`path_id` = cd1.`category_id`) LEFT JOIN `" . DB_PREFIX . "category_description` cd2 ON (cp.`category_id` = cd2.`category_id`) WHERE cd1.`language_id` = '" . (int)$this->config->get('config_language_id') . "' AND cd2.`language_id` = '" . (int)$this->config->get('config_language_id') . "'";

		if (!empty($data['filter_name'])) {
			$sql .= " AND cd2.`name` LIKE '%" . $this->db->escape((string)$data['filter_name']) . "%'";
		}

		$sql .= " GROUP BY cp.`category_id`";

		$sort_data = [
			'name',
			'sort_order'
		];

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY `" . $data['sort'] . "`";
		} else {
			$sql .= " ORDER BY `sort_order`";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getDescriptions($category_id) {
		$category_description_data = [];

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_description` WHERE `category_id` = '" . (int)$category_id . "'");

		foreach ($query->rows as $result) {
			$category_description_data[$result['language_id']] = [
				'name'             => $result['name'],
				'meta_title'       => $result['meta_title'],
				'meta_description' => $result['meta_description'],
				'meta_keyword'     => $result['meta_keyword'],
				'description'      => $result['description']
			];
		}

		return $category_description_data;
	}

	public function getPaths($category_id) {
		$query = $this->db->query("SELECT `category_id`, `path_id`, `level` FROM `" . DB_PREFIX . "category_path` WHERE `category_id` = '" . (int)$category_id . "' ORDER BY `level` ASC");

		return $query->rows;
	}

	public function getFilters($category_id) {
		$category_filter_data = [];

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_filter` WHERE `category_id` = '" . (int)$category_id . "'");

		foreach ($query->rows as $result) {
			$category_filter_data[] = $result['filter_id'];
		}

		return $category_filter_data;
	}

	public function getSeoUrls($category_id) {
		$category_seo_url_data = [];

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "seo_url` WHERE `key` = 'path' AND `value` = '" . $this->db->escape($this->getPath($category_id)) . "'");

		foreach ($query->rows as $result) {
			$category_seo_url_data[$result['store_id']][$result['language_id']] = $result['keyword'];
		}

		return $category_seo_url_data;
	}

	public function getStores($category_id) {
		$category_store_data = [];

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_to_store` WHERE `category_id` = '" . (int)$category_id . "'");

		foreach ($query->rows as $result) {
			$category_store_data[] = $result['store_id'];
		}

		return $category_store_data;
	}

	public function getLayouts($category_id) {
		$category_layout_data = [];

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_to_layout` WHERE `category_id` = '" . (int)$category_id . "'");

		foreach ($query->rows as $result) {
			$category_layout_data[$result['store_id']] = $result['layout_id'];
		}

		return $category_layout_data;
	}

	public function getTotalCategories() {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "category`");

		return $query->row['total'];
	}

	public function getTotalCategoriesByLayoutId($layout_id) {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "category_to_layout` WHERE `layout_id` = '" . (int)$layout_id . "'");

		return $query->row['total'];
	}
}
