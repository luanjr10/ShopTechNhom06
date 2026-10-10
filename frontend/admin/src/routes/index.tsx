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
import AdminChatPage from "../pages/AdminChat";
import SellerTradeIn from "../pages/seller/SellerTradeIn";
import SellerVouchers from "../pages/seller/SellerVouchers";
import SellerInstallments from "../pages/seller/SellerInstallments";
import SellerAffiliate from "../pages/seller/SellerAffiliate";
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
import SellerChat from "../pages/seller/SellerChat";

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

      { path: "/products", element: <RequireRole roles={["admin", "employee"]} module="products"><ManageProductsPage /></RequireRole> },
      { path: "/categories", element: <RequireRole roles={["admin", "employee"]} module="categories"><ManageCategoriesPage /></RequireRole> },
      { path: "/employee", element: <RequireRole roles={["admin"]}><ManageEmployeePage /></RequireRole> },
      { path: "/customers", element: <RequireRole roles={["admin", "employee"]} module="customers"><ManageCustomerPage /></RequireRole> },
      { path: "/home-highlights", element: <RequireRole roles={["admin", "employee"]} module="home_highlights"><HomeHighlightsPage /></RequireRole> },
      { path: "/brands", element: <RequireRole roles={["admin", "employee"]} module="brands"><ManageBrandsPage /></RequireRole> },
      { path: "/sellers", element: <RequireRole roles={["admin", "employee"]} module="seller_applications"><ManageSellersPage /></RequireRole> },
      { path: "/stores", element: <RequireRole roles={["admin", "employee"]} module="stores"><ManageStoresPage /></RequireRole> },
      { path: "/commissions", element: <RequireRole roles={["admin", "employee"]} module="commissions"><ManageCommissionsPage /></RequireRole> },
      { path: "/support-chat", element: <RequireRole roles={["admin", "employee"]} module="support_chat"><AdminChatPage /></RequireRole> },
      { path: "/withdrawals", element: <RequireRole roles={["admin", "employee"]} module="withdrawals"><ManageWithdrawalsPage /></RequireRole> },
      { path: "/platform-funds", element: <RequireRole roles={["admin", "employee"]} module="platform_funds"><PlatformFundsPage /></RequireRole> },
      { path: "/orders", element: <RequireRole roles={["admin", "employee"]} module="orders"><ManageOrdersPage /></RequireRole> },
      { path: "/reviews", element: <RequireRole roles={["admin", "employee"]} module="reviews"><ManageReviewsPage /></RequireRole> },

      { path: "/seller/stores", element: <RequireRole roles={["seller"]}><SellerStores /></RequireRole> },
      { path: "/seller/products", element: <RequireRole roles={["seller"]}><SellerProducts /></RequireRole> },
      { path: "/seller/orders", element: <RequireRole roles={["seller"]}><SellerOrders /></RequireRole> },
      { path: "/seller/inventory", element: <RequireRole roles={["seller"]}><SellerInventory /></RequireRole> },
      { path: "/seller/revenue", element: <RequireRole roles={["seller"]}><SellerRevenue /></RequireRole> },
      { path: "/seller/wallet", element: <RequireRole roles={["seller"]}><SellerWallet /></RequireRole> },
      { path: "/seller/withdrawals", element: <RequireRole roles={["seller"]}><SellerWithdrawals /></RequireRole> },
      { path: "/seller/customers", element: <RequireRole roles={["seller"]}><SellerCustomers /></RequireRole> },
      { path: "/seller/returns", element: <RequireRole roles={["seller"]}><SellerReturns /></RequireRole> },
      { path: "/seller/vouchers", element: <RequireRole roles={["seller"]}><SellerVouchers /></RequireRole> },
      { path: "/seller/installments", element: <RequireRole roles={["seller"]}><SellerInstallments /></RequireRole> },
      { path: "/seller/affiliate", element: <RequireRole roles={["seller"]}><SellerAffiliate /></RequireRole> },
      { path: "/seller/trade-in", element: <RequireRole roles={["seller"]}><SellerTradeIn /></RequireRole> },
      { path: "/seller/chat", element: <RequireRole roles={["seller"]}><SellerChat /></RequireRole> },
      { path: "/seller/reviews", element: <RequireRole roles={["seller"]}><SellerReviews /></RequireRole> },
      { path: "/seller/settings", element: <Navigate to="/settings" replace /> },

      { path: "/settings", element: <RequireRole roles={["admin", "seller", "employee"]}><SettingsPage /></RequireRole> },

      { path: "/messages", element: <ComingSoon title="Tin nhắn" /> },
    ],
  },
];
