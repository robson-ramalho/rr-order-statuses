=== RR Order Statuses ===
Contributors: robson.ramalho
Tags: woocommerce, order status, custom order status, orders
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create, rename and remove custom WooCommerce order statuses from one simple screen.

== Description ==

RR Order Statuses adds a screen under **WooCommerce > Order statuses** where you manage your own order statuses, such as "Shipped", "In transit" or "Awaiting pickup", in addition to the WooCommerce defaults.

* Add a status by typing its name. The slug is created automatically.
* Rename a status at any time. Orders keep their status, because the slug never changes.
* Choose whether each status counts as paid, so it appears in reports and in "paid" order checks.
* Every status gets a "Change status to ..." bulk action on the orders list.
* A status that still has orders is never removed by accident.
* Works with both order storage modes (HPOS and the legacy posts table).
* Shop managers can use it, not only administrators.
* No add-ons, no upsells, no external requests.

Customers see the status name on the My Account orders page, just like any WooCommerce status.

= Moving from another order status plugin =

On first run the plugin imports statuses created with WooCommerce Order Status Manager, if they are still in the database, so existing orders keep showing the right status. Other plugins or themes can supply their own starting list with the `rros_initial_statuses` filter.

== Installation ==

1. Install and activate WooCommerce.
2. Upload the `rr-order-statuses` folder to `/wp-content/plugins/`, or install the plugin from the Plugins screen.
3. Activate the plugin.
4. Go to **WooCommerce > Order statuses**, fill in the last row and save.

== Frequently Asked Questions ==

= Where do I add a new status? =

Under WooCommerce > Order statuses. Type the name in the empty last row and click Save Changes.

= How do I remove a status? =

Clear its name and save. If orders are still using that status, it is kept and a message tells you how many orders to move first.

= Why is the slug limited to 17 characters? =

WordPress stores order statuses in a 20 character field and WooCommerce adds the "wc-" prefix.

= Does it send e-mails when an order changes to my status? =

No. The status is shown in the admin and on the customer's My Account page.

= What happens to my statuses if I uninstall the plugin? =

Deleting the plugin removes its settings. Orders keep the status stored in the database; reinstalling the plugin and creating a status with the same slug shows them again.

== Screenshots ==

1. The Order statuses screen.

== Changelog ==

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.0.0 =
First release.
