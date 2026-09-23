import TabIntroduce from "../components/tabintroduce/index";
import { TabAds } from "../components/tabads/index";
import TabCategory from "../components/tabcategory/index";
import DealShock from "../components/dealShock/index";
import FlashSaleSection from "../components/flashSale/index";
import DailyDealsSection from "../components/dailyDeals/index";
import Products from "../components/products/index";
function Home() {
  return (
    <>
      <div className="flex flex-col mx-auto w-full max-w-[1220px] px-4 py-2 gap-2">
        <div className="flex flex-row items-stretch gap-3 mb-2">
          <TabCategory />
          <TabAds />
          <TabIntroduce />
        </div>
        <DealShock/>
        <FlashSaleSection />
        <DailyDealsSection />
        <Products/>
      </div>
    </>
  );
}

export default Home;
