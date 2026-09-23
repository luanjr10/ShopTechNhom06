import { useState } from "react";
import Header from "../Header";
import Sidebar from "../Sidebar";
import Main from "../Main";

function LayoutDefault() {
  const [sidebarOpen, setSidebarOpen] = useState(false);

  return (
    <div className="flex h-screen overflow-hidden">
      <Sidebar sidebarOpen={sidebarOpen} setSidebarOpen={setSidebarOpen} />

      <div className="relative flex flex-col flex-1 overflow-y-auto overflow-x-hidden">
        <Header sidebarOpen={sidebarOpen} setSidebarOpen={setSidebarOpen} />

        <Main />
      </div>
    </div>
  );
}

export default LayoutDefault;
