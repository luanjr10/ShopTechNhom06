import { Navigate } from "react-router-dom";
import Home from "../pages/Home";
import SettingsPage from "../pages/Settings";
import ForgotPasswordPage from "../pages/ForgotPassword";
import ManageBrandsPage from "../pages/ManageBrands";
import ManageCategoriesPage from "../pages/ManageCategory";
import ManageCustomerPage from "../pages/ManageCustomer";
import ManageEmployeePage from "../pages/ManageEmployee";
import ManageProductsPage from "../pages/ManageProducts";
import HomeHighlightsPage from "../pages/HomeHighlights";
import ManageSellersPage from "../pages/ManageSellers";
import ManageStoresPage from "../pages/ManageStores";
import ManageCommissionsPage from "../pages/ManageCommissions";
import ManageWithdrawalsPage from "../pages/ManageWithdrawals";
import ManageCouponsPage from "../pages/ManageCoupons";
import ManageOrdersPage from "../pages/ManageOrders";
import ManageReviewsPage from "../pages/ManageReviews";
import PlatformFundsPage from "../pages/PlatformFunds";
import ComingSoon from "../pages/ComingSoon";
import LoginPage from "../pages/Login";
import LayoutDefault from "../partials/layout";
import RequireRole from "../components/RequireRole";
import SellerStores from "../pages/seller/SellerStores";
import SellerProducts from "../pages/seller/SellerProducts";
import SellerOrders from "../pages/seller/SellerOrders";
import SellerInventory from "../pages/seller/SellerInventory";
import SellerRevenue from "../pages/seller/SellerRevenue";
import SellerWallet from "../pages/seller/SellerWallet";
import SellerWithdrawals from "../pages/seller/SellerWithdrawals";
import SellerCustomers from "../pages/seller/SellerCustomers";
import SellerReturns from "../pages/seller/SellerReturns";
import SellerReviews from "../pages/seller/SellerReviews";

// "/mục quản lý" chỉ admin/nhân viên có quyền vào được, "/seller/mục" chỉ
// seller vào được — kể cả gõ thẳng URL cũng bị RequireRole đá về "/". Chốt
// chặn THẬT nằm ở backend (middleware role + permission:module,ability trong
// routes/api/admin.php + brands/categories/products.php, role/store.owner
// trong routes/api/seller.php).
export const routes = [
  {
    path: "/login",
    element: <LoginPage />,
  },
  {
    path: "/quen-mat-khau",
    element: <ForgotPasswordPage />,
  },
  {
    element: (
      <RequireRole roles={["admin", "seller", "employee"]}>
        <LayoutDefault />
      </RequireRole>
    ),
    children: [
      { path: "/", element: <Home /> },

      // Admin — quản lý catalog + sàn TMĐT tổng (nhân viên vào được NẾU được cấp quyền view module đó).
      { path: "/products", element: <RequireRole roles={["admin", "employee"]} module="products"><ManageProductsPage /></RequireRole> },
      { path: "/categories", element: <RequireRole roles={["admin", "employee"]} module="categories"><ManageCategoriesPage /></RequireRole> },
      // Nhân viên + phân quyền — CHỈ admin thật, không delegate được (tránh leo thang quyền).
      { path: "/employee", element: <RequireRole roles={["admin"]}><ManageEmployeePage /></RequireRole> },
      { path: "/customers", element: <RequireRole roles={["admin", "employee"]} module="customers"><ManageCustomerPage /></RequireRole> },
      { path: "/home-highlights", element: <RequireRole roles={["admin", "employee"]} module="home_highlights"><HomeHighlightsPage /></RequireRole> },
      { path: "/brands", element: <RequireRole roles={["admin", "employee"]} module="brands"><ManageBrandsPage /></RequireRole> },
      { path: "/sellers", element: <RequireRole roles={["admin", "employee"]} module="seller_applications"><ManageSellersPage /></RequireRole> },
      { path: "/stores", element: <RequireRole roles={["admin", "employee"]} module="stores"><ManageStoresPage /></RequireRole> },
      { path: "/commissions", element: <RequireRole roles={["admin", "employee"]} module="commissions"><ManageCommissionsPage /></RequireRole> },
      { path: "/withdrawals", element: <RequireRole roles={["admin", "employee"]} module="withdrawals"><ManageWithdrawalsPage /></RequireRole> },
      { path: "/platform-funds", element: <RequireRole roles={["admin", "employee"]} module="platform_funds"><PlatformFundsPage /></RequireRole> },
      { path: "/vouchers", element: <RequireRole roles={["admin", "employee"]} module="vouchers"><ManageCouponsPage /></RequireRole> },
      { path: "/orders", element: <RequireRole roles={["admin", "employee"]} module="orders"><ManageOrdersPage /></RequireRole> },
      { path: "/reviews", element: <RequireRole roles={["admin", "employee"]} module="reviews"><ManageReviewsPage /></RequireRole> },

      // Seller — Kênh người bán (quản lý gian hàng CỦA MÌNH)
      { path: "/seller/stores", element: <RequireRole roles={["seller"]}><SellerStores /></RequireRole> },
      { path: "/seller/products", element: <RequireRole roles={["seller"]}><SellerProducts /></RequireRole> },
      { path: "/seller/orders", element: <RequireRole roles={["seller"]}><SellerOrders /></RequireRole> },
      { path: "/seller/inventory", element: <RequireRole roles={["seller"]}><SellerInventory /></RequireRole> },
      { path: "/seller/revenue", element: <RequireRole roles={["seller"]}><SellerRevenue /></RequireRole> },
      { path: "/seller/wallet", element: <RequireRole roles={["seller"]}><SellerWallet /></RequireRole> },
      { path: "/seller/withdrawals", element: <RequireRole roles={["seller"]}><SellerWithdrawals /></RequireRole> },
      { path: "/seller/customers", element: <RequireRole roles={["seller"]}><SellerCustomers /></RequireRole> },
      { path: "/seller/returns", element: <RequireRole roles={["seller"]}><SellerReturns /></RequireRole> },
      { path: "/seller/reviews", element: <RequireRole roles={["seller"]}><SellerReviews /></RequireRole> },
      // Route cũ — giữ redirect để không vỡ link/bookmark có sẵn.
      { path: "/seller/settings", element: <Navigate to="/settings" replace /> },

      // Cài đặt tài khoản (mọi role) + gian hàng (riêng seller, xem SettingsPage).
      { path: "/settings", element: <RequireRole roles={["admin", "seller", "employee"]}><SettingsPage /></RequireRole> },

      // Giữ chỗ — chưa triển khai
      { path: "/messages", element: <ComingSoon title="Tin nhắn" /> },
    ],
  },
];
