from flask import Flask, request, jsonify, send_file # flask app functionality
import matplotlib.pyplot as plt # graphing
from datetime import datetime
from io import BytesIO # for sending image buffer
from db_URI import db_URI # connection to db held elsewhere
# sqlalchemy ORM models:
from models import (db, StoreModel, ItemModel, OrderModel, OrderItemModel, FeesModel, ItemStatsModel,
                    OriginModel, ReviewModel, SalesTaxModel, ShippingModel, ShipToModel, StoreStatsModel)
from sqlalchemy import select, extract, func, asc, and_ # query creation methods and functions
from sqlalchemy.sql import Select # query object for type hinting
from sqlalchemy.engine import Row # object for type hinting the retrieved data from db

# so pyplot doesn't try to open a UI:
plt.switch_backend('Agg')

app = Flask(__name__)

# configure sqlalechmy settings
app.config['SQLALCHEMY_DATABASE_URI'] = db_URI # connection to db
app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False # no tracking db modifications, better performance

db.init_app(app)


# used to map months returned by queries to their names
MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 
           'July', 'August', 'September', 'October', 'November', 'December']

# uncomment if creating any tables not in the db
# @app.before_request
# def createDB():
#     db.create_all()

#============================================================================================================================================
#---------------------------------- Items Dashboard Endpoints -------------------------------------------------------------------------------
#============================================================================================================================================

#---------------------------------- Item Orders Endpoint ------------------------------------------------------------------------------------

@app.get("/itemsdashboard/orders")
def orders_graph_items_dashboard() -> bytes:
    try: # retrieve query parameters
        params = request.args.to_dict()
        selectedItem : int = int(params['item'])
        selectedYear : int = int(params['year'])
        selectedMonth : int = int(params['month'])
    except Exception as e:
        return jsonify(f"Error thrown when retrieving query parameters in orders endpoint"), 500
    

    if selectedItem != 0: # if an item is chosen, the graph returned is # of orders of that item per month
        # format the query based on the selected options sent from the web server
        # select method used just as SELECT in SQL
        # select_from method used as SQL FROM, specifying the base of the later joins
        # i.e. serving as the WHERE command as well
        # extract method used to extract specified portions of a value
        # label method used in place of AS in a SQL query
        # func module used for aggregate functions
        query : Select = select(extract('month', OrderModel.date).label('orderMonth'), 
                       func.count(OrderModel.order_id).label('orderCount')).\
                select_from(OrderModel).\
                join(OrderItemModel, and_(OrderItemModel.order_id == OrderModel.order_id,
                                          OrderItemModel.item_id == selectedItem,
                                          extract('year', OrderModel.date) == selectedYear)).\
                group_by('orderMonth').\
                order_by(asc('orderMonth'))
        
        try: # execute the query on the db:
            rows : list[Row] = db.session.execute(query).all()
        except Exception as e:
            return jsonify(f"Error thrown when attempting to query database for orders graph: {e}"), 500

        try: # parse data retreived from db:
            ordersPerMonth : list[int] = [0 for _ in range(12)]
            for row in rows:
                ordersPerMonth[row.orderMonth - 1] += row.orderCount
        except Exception as e:
            return jsonify(f"Error thrown when parsing orders data received from database: {e}"), 500

        return create_graph(MONTHS, ordersPerMonth, f'{selectedYear}', '# of Orders', 
                            f'Orders Per Month in {selectedYear}'), 200
    
    
    else: # if "All items" chosen, the graph returned is # of orders per each item in the selected month

        # create the db query:
        query : Select = select(ItemModel.item_id, ItemModel.name, 
                                func.coalesce(func.count(OrderModel.order_id), 0).label('orderCount')).\
                select_from(ItemModel).\
                join(OrderItemModel, OrderItemModel.item_id == ItemModel.item_id, # isouter flag used to set join to left outer join
                     isouter=True).\
                join(OrderModel, and_(OrderModel.order_id == OrderItemModel.order_id,
                                      extract('month', OrderModel.date) == selectedMonth),
                                      isouter=True).\
                group_by(ItemModel.item_id, ItemModel.name).\
                order_by(asc(ItemModel.item_id))
        
        try: # execute the order on the db:
            rows : list[Row] = db.session.execute(query).all()
        except Exception as e:
            return jsonify(f"Error thrown when attempting to query database for orders graph: {e}"), 500
        
        try: # parse data received from db:
            ordersPerItem : list[int] = []
            names : list[str] = []
            for row in rows:
                ordersPerItem.append(row.orderCount)
                names.append(row.name)
        except Exception as e:
            return jsonify(f"Error thrown when parsing orders data received from database: {e}"), 500
        
        return create_graph(names, ordersPerItem, 'Items', '# of Orders', 
                            f"Orders per Item in {MONTHS[selectedMonth-1]}, {selectedYear}"), 200


#---------------------------------- Item Fees Endpoint ------------------------------------------------------------------------------------

@app.get("/itemsdashboard/fees")
def fees_graph_items_dashboard() -> bytes:
    try:  # retrieve query parameters
        params = request.args.to_dict()
        selectedItem : int = int(params['item'])
        selectedYear  : int = int(params['year'])
        selectedMonth : int = int(params['month'])
    except Exception as e:
        return jsonify(f"Error thrown when retrieving query parameters in fees endpoint"), 500
    

    if selectedItem != 0: # create graph for total fees paid per month on orders of the selected item in the selected year

        # create database query:
        query : Select = select(func.sum(FeesModel.processing + FeesModel.transaction).label('totalFees'),
                       extract('month', OrderModel.date).label('month')).\
                select_from(FeesModel).\
                join(OrderModel, and_(FeesModel.order_id == OrderModel.order_id,
                                      extract('year', OrderModel.date) == selectedYear)).\
                join(OrderItemModel, and_(FeesModel.order_id == OrderItemModel.order_id, 
                                          OrderItemModel.item_id == selectedItem)).\
                group_by('month')
                
        try: # execuet the query
            rows : list[Row] = db.session.execute(query).all()
        except Exception as e:
            return jsonify(f"Error thrown when attempting to query database for fees graph: {e}"), 500

        
        try: # parse the data received from database:
            feesPerMonth : list[float] = [0 for _ in range(12)]
            for row in rows:
                feesPerMonth[row.month-1] += row.totalFees
        except Exception as e:
            return jsonify(f"Error thrown when parsing fees data received from database: {e}"), 500

        return create_graph(MONTHS, feesPerMonth, f'{selectedYear}', 'Fees ($)', 
                            f'Total Fees per Month in {selectedYear}'), 200
    

    else: # create a graph for total fees per item in the selected month of the selected year
        
        # create database query:
        query : Select = select(func.coalesce(func.sum(FeesModel.processing + FeesModel.transaction), 0.00).label('totalFees'), 
                       ItemModel.name, ItemModel.item_id, extract('month', OrderModel.date).label('month')).\
                select_from(ItemModel).\
                join(OrderItemModel, OrderItemModel.item_id == ItemModel.item_id, isouter=True).\
                join(OrderModel, and_(OrderItemModel.order_id == OrderModel.order_id,
                                      extract('month', OrderModel.date) == selectedMonth,
                                      extract('year', OrderModel.date) == selectedYear), isouter=True).\
                join(FeesModel, FeesModel.order_id == OrderItemModel.order_id, isouter=True).\
                group_by(ItemModel.item_id, ItemModel.name, 'month').\
                order_by(asc(ItemModel.item_id))
        
        try: # execute query on database:
            rows : list[Row] = db.session.execute(query).all()
        except Exception as e:
            return jsonify(f"Error thrown when attempting to query database for fees graph: {e}"), 500
        
        
        try: # parse data received from database
            itemNames : list[str] = []
            feesPerItem : list[float] = []
            for row in rows:
                itemNames.append(row.name)
                feesPerItem.append(row.totalFees if row.month != None else 0.00)
        except Exception as e:
            return jsonify(f"Error thrown when parsing fees data received from database: {e}"), 500
        
        return create_graph(itemNames, feesPerItem, 'Items', 'Total Fees ($)', 
                            f"Total Fees per item in {MONTHS[selectedMonth-1]}, {selectedYear}"), 200


#---------------------------------- Item shipping Endpoint ------------------------------------------------------------------------------------

@app.get("/itemsdashboard/shipping")
def shipping_graph_items_dashboard() -> bytes:
    try:  # retrieve query parameters
        params = request.args.to_dict()
        selectedItem : int = int(params['item'])
        selectedYear  : int = int(params['year'])
        selectedMonth : int = int(params['month'])
    except Exception as e:
        return jsonify(f"Error thrown when retrieving query parameters in shipping endpoint"), 500
    
    
    if selectedItem != 0: # graph the shipping costs of a single item per month for a selected year:
        
        # create database query:
        query : Select = select(func.sum(ShippingModel.cost).label('cost'), 
                                extract('month', OrderModel.date).label('month')).\
                select_from(OrderItemModel).\
                join(OrderModel, and_(OrderItemModel.item_id == selectedItem, 
                                      OrderItemModel.order_id == OrderModel.order_id)).\
                join(ShippingModel, ShippingModel.order_id == OrderModel.order_id).\
                group_by('month')
                
        try: # execute the query on the db:
            rows : list[Row] = db.session.execute(query).all()
        except Exception as e:
            return jsonify(f"Error thrown when attempting to query database for shipping graph: {e}"), 500
        
        try: # parse the data received from db:
            shipping : list[float] = [0 for _ in range(12)]
            for i in range(len(rows)):
                shipping[rows[i].month - 1] += rows[i].cost
        except Exception as e:
            return jsonify(f"Error thrown when parsing shipping data received from database: {e}"), 500

        return create_graph(MONTHS, shipping, f"{selectedYear}", "Total Shipping Costs ($)", 
                        f"Total Shipping Cost per Month in {selectedYear}"), 200
    
    
    else: # graph the shipping fees per item over the specified year

        # create the db query:
        query : Select = select(func.coalesce(func.sum(ShippingModel.cost), 0.00).label('cost'), 
                                ItemModel.name, ItemModel.item_id, extract('month', OrderModel.date).label('month')).\
                         select_from(ItemModel).\
                         join(OrderItemModel, ItemModel.item_id == OrderItemModel.item_id, isouter=True).\
                         join(OrderModel, and_(OrderItemModel.order_id == OrderModel.order_id,
                                               extract('year', OrderModel.date) == selectedYear,
                                               extract('month', OrderModel.date) == selectedMonth),
                                               isouter=True).\
                         join(ShippingModel, OrderItemModel.order_id == ShippingModel.order_id, isouter=True).\
                         group_by(ItemModel.item_id, ItemModel.name, 'month').\
                         order_by(ItemModel.item_id)
        
        try: # execuet the query on the db:
            rows : list[Row] = db.session.execute(query).all()
        except Exception as e:
            return jsonify(f"Error thrown when attempting to query database for shipping graph: {e}"), 500

        try: # parse the data received from db:
            names : list[str] = []
            shippingCostPerItem = []
            for row in rows:
                names.append(row.name)
                shippingCostPerItem.append(row.cost if row.month != None else 0.00)
        except Exception as e:
            return jsonify(f"Error thrown when parsing shipping data received from database: {e}"), 500

        return create_graph(names, shippingCostPerItem, "Items", "Total Shipping Costs ($)",
                            f"Total Shipping Costs Per Item in {MONTHS[selectedMonth-1]}, {selectedYear}"), 200


#---------------------------------- Item Views Endpoint ------------------------------------------------------------------------------------

@app.get("/itemsdashboard/views")
def views_graph_items_dashboard() -> bytes:
    try:  # retrieve query parameters
        params = request.args.to_dict()
        selectedItem : int = int(params['item'])
        selectedYear  : int = int(params['year'])
        selectedMonth : int = int(params['month'])
    except Exception as e:
        return jsonify(f"Error thrown when retrieving query parameters in views endpoint"), 500
    

    if selectedItem != 0: # graph the views per month of a single item

        # create the db query:
        query : Select = select(ItemStatsModel.num_views, ItemStatsModel.month).\
                         select_from(ItemStatsModel).\
                         where(ItemStatsModel.year == selectedYear,
                               ItemStatsModel.item_id == selectedItem)
                         
        try: # execute the query on the db:
            rows : list[Row] = db.session.execute(query).all()
        except Exception as e:
            return jsonify(f"Error thrown when attempting to query database for views graph: {e}"), 500


        try: # parse the data received from the db:
            viewsPerMonth : list[int] = [0 for _ in range(12)]
            for row in rows:
                viewsPerMonth[monthToNum(row.month)-1] += row.num_views
        except Exception as e:
            return jsonify(f"Error thrown when parsing views data received from database: {e}"), 500

        return create_graph(MONTHS, viewsPerMonth, f"{selectedYear}", "Views Per Month",
                            f"Item Views Per Month in {selectedYear}"), 200

    else: # graph the total views per item in a year

        query : Select = select(func.coalesce(func.sum(ItemStatsModel.num_views), 0).label('num_views'), 
                                ItemStatsModel.item_id, ItemModel.name,).\
                         select_from(ItemModel).\
                         join(ItemStatsModel, and_(ItemModel.item_id == ItemStatsModel.item_id,
                                                   ItemStatsModel.year == selectedYear,
                                                   ItemStatsModel.month == MONTHS[selectedMonth-1]),
                                                   isouter=True).\
                         group_by(ItemStatsModel.item_id, ItemModel.name).\
                         order_by(asc(ItemStatsModel.item_id))
        
        try: # parse the data received from the db:
            rows : list[Row] = db.session.execute(query).all()
        except Exception as e:
            return jsonify(f"Error thrown when attempting to query database for views graph: {e}"), 500
        
        try: # parse the data received from the db:
            names : list[str] = []
            viewsPerItem : list[int] = [0 for _ in range(len(rows))]
            for i, row in enumerate(rows):
                names.append(row.name)
                viewsPerItem[i] += row.num_views
        except Exception as e:
            return jsonify(f"Error thrown when parsing views data received from database: {e}"), 500
        
        return create_graph(names, viewsPerItem, "Items", "Total Views Per Item",
                            f"Total Views Per Item in {MONTHS[selectedMonth-1]}, {selectedYear}"), 200
                        

#---------------------------------- Item Reviews Endpoint ------------------------------------------------------------------------------------

@app.get("/itemsdashboard/reviews")
def reviews_graph_items_dashboard() -> bytes:
    try:  # retrieve query parameters
        params = request.args.to_dict()
        selectedItem : int = int(params['item'])
        selectedYear  : int = int(params['year'])
        selectedMonth : int = int(params['month'])
    except Exception as e:
        return jsonify(f"Error thrown when retrieving query parameters in reviews endpoint"), 500
    
    if selectedItem != 0: # graph the average stars per month for a single item

        # create the db query:
        query : Select = select(func.avg(ReviewModel.num_stars).label('num_stars'),
                                func.count(ReviewModel.item_id).label("num_reviews"),
                                extract('month', OrderModel.date).label('month')).\
                         select_from(ReviewModel).\
                         join(OrderModel, and_(ReviewModel.order_id == OrderModel.order_id,
                                               ReviewModel.item_id == selectedItem,
                                               extract('year', OrderModel.date) == selectedYear)).\
                         group_by('month').\
                         order_by('month')
        
        try: # execute the query on the db:
            rows : list[Row] = db.session.execute(query).all()
        except Exception as e:
            return jsonify(f"Error thrown when attempting to query database for reviews graph: {e}"), 500
        

        try: # parse the data received from db:
            starsPerMonth : list[int] = [0 for _ in range(12)]
            monthsWithCounts : list[list[str, int]] = [[month, 0] for month in MONTHS]
            for row in rows:
                starsPerMonth[row.month-1] = row.num_stars
                monthsWithCounts[row.month-1][1] = row.num_reviews
        except Exception as e:
            return jsonify(f"Error thrown when parsing reviews data received from database: {e}"), 500
        
        return create_graph([f"{month[0]} ({month[1]})" for month in monthsWithCounts], starsPerMonth, 
                            f"{selectedYear} + # of Reviews Per Month", "Avg Stars Per Review",
                            f"Average Stars Per Review in {selectedYear}"), 200
    
    else: # graph the average number of stars per review of each item in a single year

        query : Select = select(func.coalesce(func.avg(ReviewModel.num_stars), 0).label('num_stars'),
                                func.count(ReviewModel.item_id).label('num_reviews'),
                                ItemModel.item_id, ItemModel.name, extract('month', OrderModel.date).label('month')).\
                         select_from(ItemModel).\
                         join(ReviewModel, ReviewModel.item_id == ItemModel.item_id, isouter=True).\
                         join(OrderModel, and_(OrderModel.order_id == ReviewModel.order_id,
                                               extract('year', OrderModel.date) == selectedYear,
                                               extract('month', OrderModel.date) == selectedMonth),
                                               isouter=True).\
                         group_by(ItemModel.item_id, ItemModel.name, 'month').\
                         order_by(asc(ItemModel.item_id))
        
        try: # execute query on db:
            rows : list[Row] = db.session.execute(query).all()
        except Exception as e:
            return jsonify(f"Error thrown when attempting to query database for reviews graph: {e}"), 500
        
        try: # parse the data received from db:
            starsPerItem : list[int] = []
            namesWithCounts : list[list[str, int]] = []
            for row in rows:
                starsPerItem.append(row.num_stars if row.month != None else 0)
                newPair : list[str, int] = [row.name, row.num_reviews if row.month != None else 0]
                namesWithCounts.append(newPair)
        except Exception as e:
            return jsonify(f"Error thrown when parsing reviews data received from database: {e}"), 500


        return create_graph([f"{name[0]} ({name[1]})" for name in namesWithCounts], starsPerItem, "Items + # of Reviews",
                            "Avg Stars Per Review", f"Average Stars Per Review in {MONTHS[selectedMonth-1]}, {selectedYear}"),\
                            200

#---------------------------------- Item Favorites Endpoint ------------------------------------------------------------------------------------

@app.get("/itemsdashboard/favorites")
def favorites_graph_items_dashboard() -> bytes:
    try:  # retrieve query parameters
        params = request.args.to_dict()
        selectedItem : int = int(params['item'])
        selectedYear  : int = int(params['year'])
        selectedMonth : int = int(params['month'])
    except Exception as e:
        return jsonify(f"Error thrown when retrieving query parameters in favorites endpoint"), 500
    

    if selectedItem != 0: # graph the favorites per month of a single item

        # create the db query:
        query : Select = select(ItemStatsModel.num_favorites, ItemStatsModel.month).\
                         select_from(ItemStatsModel).\
                         where(ItemStatsModel.year == selectedYear,
                               ItemStatsModel.item_id == selectedItem)
                         
        try: # execute the query on the db:
            rows : list[Row] = db.session.execute(query).all()
        except Exception as e:
            return jsonify(f"Error thrown when attempting to query database for favorites graph: {e}"), 500


        try: # parse the data received from the db:
            favoritesPerMonth : list[int] = [0 for _ in range(12)]
            for row in rows:
                favoritesPerMonth[monthToNum(row.month)-1] += row.num_favorites
        except Exception as e:
            return jsonify(f"Error thrown when parsing favorites data received from database: {e}"), 500

        return create_graph(MONTHS, favoritesPerMonth, f"{selectedYear}", "Favorites Per Month",
                            f"Item Favorites Per Month in {selectedYear}"), 200

    else: # graph the total favorites per item in a year

        query : Select = select(func.coalesce(func.sum(ItemStatsModel.num_favorites), 0).label('num_favorites'), 
                                ItemStatsModel.item_id, ItemModel.name).\
                         select_from(ItemModel).\
                         join(ItemStatsModel, and_(ItemModel.item_id == ItemStatsModel.item_id,
                                                   ItemStatsModel.year == selectedYear,
                                                   ItemStatsModel.month == MONTHS[selectedMonth-1]),
                                                   isouter=True).\
                         group_by(ItemStatsModel.item_id, ItemModel.name).\
                         order_by(asc(ItemStatsModel.item_id))
        
        try: # parse the data received from the db:
            rows : list[Row] = db.session.execute(query).all()
        except Exception as e:
            return jsonify(f"Error thrown when attempting to query database for favorites graph: {e}"), 500
        
        try: # parse the data received from the db:
            names : list[str] = []
            favoritesPerItem : list[int] = [0 for _ in range(len(rows))]
            for i, row in enumerate(rows):
                names.append(row.name)
                favoritesPerItem[i] += row.num_favorites
        except Exception as e:
            return jsonify(f"Error thrown when parsing favorites data received from database: {e}"), 500
        
        return create_graph(names, favoritesPerItem, "Items", "Total Favorites Per Item",
                            f"Total Favorites Per Item in {MONTHS[selectedMonth-1]}, {selectedYear}"), 200

#============================================================================================================================================
#---------------------------------- Store Dashboard Endpoints -------------------------------------------------------------------------------
#============================================================================================================================================

#---------------------------------- Store Reviews Endpoint ------------------------------------------------------------------------------------

@app.get("/storedashboard/reviews")
def reviews_graph_store_dashboard() -> bytes:
    try: # retrieve query parameters
        params = request.args.to_dict()
        selectedStore : int = int(params['store'])
        selectedYear : int = int(params['year'])
    except Exception as e:
        return jsonify(f"Error thrown when retrieving query parameters in reviews endpoint"), 500

    # create the query:
    query : Select = select(func.coalesce(func.avg(ReviewModel.num_stars), 0).label('num_stars'), 
                            extract('month', OrderModel.date).label('month')).\
                        select_from(ReviewModel).\
                        join(OrderModel, and_(ReviewModel.order_id == OrderModel.order_id,
                                            extract('year', OrderModel.date) == selectedYear,
                                            OrderModel.store_id == selectedStore)).\
                        group_by(extract('month', OrderModel.date)).\
                        order_by('month')
    
    try: # execute the query of the db:
        rows : list[Row] = db.session.execute(query).all()
    except Exception as e:
        return jsonify(f"Error thrown when attempting to query database for reviews graph: {e}"), 500
    
    try: # parse the data retrieved from the db:
        starsPerMonth : list[int] = [0 for _ in range(12)]
        for row in rows:
            starsPerMonth[row.month-1] = row.num_stars
    except Exception as e:
        return jsonify(f"Error thrown when parsing reviews data received from database: {e}"), 500

    return create_graph(MONTHS, starsPerMonth, f"{selectedYear}", "Average Stars per Review",
                        f"Average # of Stars per Review in the months of {selectedYear}"), 200


#---------------------------------- Store Follows Endpoint ------------------------------------------------------------------------------------

@app.get("/storedashboard/follows")
def follows_graph_store_dashboard() -> bytes:
    try: # retrieve query parameters
        params = request.args.to_dict()
        selectedStore : int = int(params['store'])
        selectedYear : int = int(params['year'])
    except Exception as e:
        return jsonify(f"Error thrown when retrieving query parameters in follows endpoint"), 500
    
    # create the query:
    query : Select = select(StoreStatsModel.num_follows, StoreStatsModel.month).\
                     select_from(StoreStatsModel).\
                     where(and_(StoreStatsModel.store_id == selectedStore,
                                StoreStatsModel.year == selectedYear))
    
    try: # execute query on db:
        rows : list[Row] = db.session.execute(query).all()
    except Exception as e:
        return jsonify(f"Error thrown when attempting to query database for follows graph: {e}"), 500

    try: # parse the data retrived from db:
        followsPerMonth : list[int] = [0 for _ in range(12)]
        for row in rows:
            followsPerMonth[MONTHS.index(row.month)] = row.num_follows
    except Exception as e:
        return jsonify(f"Error thrown when parsing follows data received from database: {e}"), 500

    return create_graph(MONTHS, followsPerMonth, f"{selectedYear}", "# Shop Follows",
                        f"# Shop Follows per Month in {selectedYear}"), 200


#---------------------------------- Store Orders Endpoint ------------------------------------------------------------------------------------

@app.get("/storedashboard/orders")
def orders_graph_store_dashboard() -> bytes:
    try: # retrieve query parameters
        params = request.args.to_dict()
        selectedStore : int = int(params['store'])
        selectedYear : int = int(params['year'])
    except Exception as e:
        return jsonify(f"Error thrown when retrieving query parameters in orders endpoint"), 500
    
    # create the query:
    query : Select = select(func.count(OrderModel.order_id).label('order_count'), 
                            extract('month', OrderModel.date).label('month')).\
                     select_from(OrderModel).\
                     where(and_(extract('year', OrderModel.date) == selectedYear, 
                                OrderModel.store_id == selectedStore)).\
                    group_by(extract('month', OrderModel.date)).\
                    order_by('month')
    
    try: # execute query on db:
        rows : list[Row] = db.session.execute(query).all()
    except Exception as e:
        return jsonify(f"Error thrown when attempting to query database for orders graph: {e}"), 500
    
    try: # parse the retrieved data:
        ordersPerMonth : list[int] = [0 for _ in range(12)]
        for row in rows:
            ordersPerMonth[row.month - 1] = row.order_count
    except Exception as e:
        return jsonify(f"Error thrown when attempting to query database for orders graph: {e}"), 500

    return create_graph(MONTHS, ordersPerMonth, f"{selectedYear}", "# Orders",
                        f"# Orders Per Month in {selectedYear}")


#---------------------------------- Store Sales Tax Endpoint ------------------------------------------------------------------------------------

@app.get("/storedashboard/salestax")
def salestax_graph_store_dashboard() -> bytes:
    try: # retrieve query parameters
        params = request.args.to_dict()
        selectedStore : int = int(params['store'])
        selectedYear : int = int(params['year'])
    except Exception as e:
        return jsonify(f"Error thrown when retrieving query parameters in sales tax endpoint"), 500
    
    # create the query:
    query : Select = select(func.sum(SalesTaxModel.amount).label('totalSTax'),
                            extract('month', OrderModel.date).label('month')).\
                     select_from(SalesTaxModel).\
                     join(OrderModel, and_(SalesTaxModel.order_id == OrderModel.order_id,
                                           extract('year', OrderModel.date) == selectedYear,
                                           OrderModel.store_id == selectedStore)).\
                     group_by(extract('month', OrderModel.date)).\
                     order_by('month')
    
    try: # execute query on db:
        rows : list[Row] = db.session.execute(query).all()
    except Exception as e:
        return jsonify(f"Error thrown when attempting to query database for sales tax graph: {e}"), 500
    
    try: # parse the retrieved data:
        taxPerMonth : list[float] = [0.00 for _ in range(12)]
        for row in rows:
            taxPerMonth[row.month - 1] = row.totalSTax
    except Exception as e:
        return jsonify(f"Error thrown when attempting to query database for sales tax graph: {e}"), 500

    
    return create_graph(MONTHS, taxPerMonth, f"{selectedYear}", "Total Sales Tax Amount",
                        f"Total Sales Tax Amount Per Month in {selectedYear}"), 200


#---------------------------------- Store Fees Endpoint ------------------------------------------------------------------------------------

@app.get("/storedashboard/fees")
def fees_graph_store_dashboard() -> bytes:
    try: # retrieve query parameters
        params = request.args.to_dict()
        selectedStore : int = int(params['store'])
        selectedYear : int = int(params['year'])
    except Exception as e:
        return jsonify(f"Error thrown when retrieving query parameters in fees endpoint"), 500
    
    # create the query:
    query : Select = select(func.sum(FeesModel.processing + FeesModel.transaction).label('totalFees'),
                            extract('month', OrderModel.date).label('month')).\
                     select_from(FeesModel).\
                     join(OrderModel, and_(FeesModel.order_id == OrderModel.order_id,
                                           extract('year', OrderModel.date) == selectedYear,
                                           OrderModel.store_id == selectedStore)).\
                    group_by(extract('month', OrderModel.date)).\
                    order_by('month')
    
    try: # execute query on db:
        rows : list[Row] = db.session.execute(query).all()
    except Exception as e:
        return jsonify(f"Error thrown when attempting to query database for fees graph: {e}"), 500
    
    try: # parse the retrieved data:
        feesPerMonth : list[float] = [0.00 for _ in range(12)]
        for row in rows:
            feesPerMonth[row.month - 1] = row.totalFees
    except Exception as e:
        return jsonify(f"Error thrown when attempting to query database for fees graph: {e}"), 500
    

    return create_graph(MONTHS, feesPerMonth, f"{selectedYear}", "Total Fee Amounts",
                        f"Total Fee Amounts Per Month in {selectedYear}"), 200


#---------------------------------- Store Reviews Endpoint ------------------------------------------------------------------------------------

@app.get("/storedashboard/visits")
def visits_graph_store_dashboard() -> bytes:
    try: # retrieve query parameters
        params = request.args.to_dict()
        selectedStore : int = int(params['store'])
        selectedYear : int = int(params['year'])
    except Exception as e:
        return jsonify(f"Error thrown when retrieving query parameters in visits endpoint"), 500
    
    # create the query:
    query : Select = select(StoreStatsModel.num_visits, StoreStatsModel.month).\
                     select_from(StoreStatsModel).\
                     where(and_(StoreStatsModel.store_id == selectedStore,
                                StoreStatsModel.year == selectedYear))
    
    try: # execute query on db:
        rows : list[Row] = db.session.execute(query).all()
    except Exception as e:
        return jsonify(f"Error thrown when attempting to query database for visits graph: {e}"), 500

    try: # parse the data retrived from db:
        visitsPerMonth : list[int] = [0 for _ in range(12)]
        for row in rows:
            visitsPerMonth[MONTHS.index(row.month)] = row.num_visits
    except Exception as e:
        return jsonify(f"Error thrown when parsing visits data received from database: {e}"), 500

    return create_graph(MONTHS, visitsPerMonth, f"{selectedYear}", "# Shop Visits",
                        f"# Shop visits per Month in {selectedYear}"), 200


#============================================================================================================================================
#---------------------------------- Graph Creation Function ---------------------------------------------------------------------------------
#============================================================================================================================================


def create_graph(x_data: list[int], y_data: list[int], x_label: str, y_label: str, graph_label: str) -> bytes:
        """Creates bargraph based on inputs from queries in one of the endpoints and returns it as an image buffer"""
        try: # create the graph based on input
            # set the x bar positions based on how many items are in the x-axis list:
            x_tick_positions = [x for x in range(len(x_data))]

            # create a matplotlib Figure and Axes:
            fig, ax = plt.subplots()

            # configure the graph options:
            
            # fontsize (int): size of the font of the x-axis categories
            fontsize = 12 # set font size
            if (num_bars := len(x_data)) > 13: # decrease font size by one for every extra x-axis variable
                fontsize -= (num_bars - 13)

            # set the graph to plot the given data, and make the bars blue:
            bars = ax.bar(x_tick_positions, y_data, color='blue')
            # set the title to the dashboard category, and make it bold:
            ax.set_title(f"{graph_label}", fontweight='bold')
            # set the bar positions along the x-axis:
            ax.set_xticks(x_tick_positions)
            # label the x-axis to be the given label; in bold:
            ax.set_xlabel(f"{x_label}", fontsize=10, fontweight='bold')
            # set the bar labels, their orientation around the tick marks, their font size, and their rotation 
            # in reference to the x-axis
            ax.set_xticklabels(x_data, ha='right', fontsize=fontsize, rotation=55)
            # label the y axis as the given y_label:
            ax.set_ylabel(f"{y_label}", rotation=90, labelpad=10, fontweight='bold')
            # get rid of the top and right sides of the graph box for visual appeal:
            for side in ["top", "right"]:
                ax.spines[side].set_visible(False)
            # Add labels on top of each bar
            for bar in bars:
                yval = bar.get_height()
                ax.text(bar.get_x() + bar.get_width() / 2, yval, f'{yval}',
                        ha='center', va='bottom')
            # make the layout autosize to the graph
            plt.tight_layout()
        except Exception as e:
            return jsonify(f"Error thrown while creating the graph: {e}"), 500
        
        try: #create the image buffer, save the image to it as a png
            buffer = BytesIO()
            plt.savefig(buffer, format='png')
            buffer.seek(0) # sets buffer back to beginning of image
            # image_bytes = buffer.getvalue()
            # ecoded_image = base64.b64encode(image_bytes)
        except Exception as e:
            return jsonify(f"Error thrown when creating graph image buffer: {e}"), 500

        # close the plot to save memory:
        plt.close()

        # return the buffer to the caller
        return send_file(buffer, mimetype='image/png', as_attachment=False)

def monthToNum(month_name: str) -> int:
    # Converts a month to its number equivalent
    return datetime.strptime(month_name, "%B").month

