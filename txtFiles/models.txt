"""Source for sqlalchemy: REST APIs with Flask and Python in 2023 course by Jose Salvatierra Fuente on O'Reilly Media"""

from flask_sqlalchemy import SQLAlchemy
from sqlalchemy import Column, Integer, ForeignKey, String, Numeric, Date, PrimaryKeyConstraint


db = SQLAlchemy()

class StoreModel(db.Model): # refers to store table with columns store_id and name
    __tablename__ = 'store'
    store_id = Column(Integer, primary_key=True)
    name = Column(String(45), nullable = False)

class ItemModel(db.Model): # refers to items table with columns item_id, store_id, name, and price
    __tablename__ = 'items'
    item_id = Column(Integer, primary_key=True)
    store_id = Column(Integer, ForeignKey('store.store_id'), nullable=False)
    name = Column(String(45), nullable=False)
    price = Column(Numeric(10, 2), nullable=False)

class OrderModel(db.Model): # refers to orders table with columns order_id, store_id, and date
    __tablename__ = 'orders'
    order_id = Column(Integer)
    store_id = Column(Integer, ForeignKey('store.store_id'))
    date = Column(Date, nullable=False)
    __table_args__ = (PrimaryKeyConstraint('order_id', 'store_id'),)

class OrderItemModel(db.Model):
    __tablename__ = 'order_items'
    item_id = Column(Integer, ForeignKey('items.item_id'), primary_key=True)
    order_id = Column(Integer, ForeignKey('orders.order_id'), primary_key=True)
    __table_args__ = (PrimaryKeyConstraint('item_id', 'order_id'),)

class ItemStatsModel(db.Model):
    __tablename__ = 'item_stats'
    item_id = Column(Integer, ForeignKey('items.item_id'))
    month = Column(String(10))
    year = Column(String(5))
    num_views = Column(Integer, nullable=False)
    num_favorites = Column(Integer, nullable=False)
    __table_args__ = (PrimaryKeyConstraint('item_id', 'year', 'month'),)

class StoreStatsModel(db.Model):
    __tablename__ = 'store_stats'
    store_id = Column(Integer, ForeignKey('store.store_id'))
    month = Column(String(10))
    year = Column(String(5))
    num_follows = Column(Integer, nullable=False)
    num_visits = Column(Integer, nullable=False)
    __table_args__ = (PrimaryKeyConstraint('store_id', 'month', 'year'),)

class FeesModel(db.Model):
    __tablename__ = 'fees'
    order_id = Column(Integer, ForeignKey('orders.order_id'), primary_key=True)
    transaction = Column(Numeric(6, 2), nullable=False)
    processing = Column(Numeric(6, 2), nullable=False)

class SalesTaxModel(db.Model):
    __tablename__ = 'sales_tax'
    order_id = Column(Integer, ForeignKey('orders.order_id'), primary_key=True)
    amount = Column(Numeric(6, 2), nullable=False)

class ShippingModel(db.Model):
    __tablename__ = 'shipping'
    order_id = Column(Integer, ForeignKey('orders.order_id'), primary_key=True)
    cost = Column(Numeric(6, 2), nullable=False)
    origin_id = Column(Integer, ForeignKey('origin.origin_id'), nullable=False)

class OriginModel(db.Model):
    __tablename__ = 'origin'
    origin_id = Column(Integer, primary_key=True)
    city = Column(String(45), nullable=False)
    state = Column(String(15), nullable=False)

class ShipToModel(db.Model):
    __tablename__ = 'ship_to'
    order_id = Column(Integer, ForeignKey('orders.order_id'), primary_key=True)
    city = Column(String(45), nullable=False)
    state = Column(String(2), nullable=False)

class ReviewModel(db.Model):
    __tablename__ = 'review'
    item_id = Column(Integer, ForeignKey('items.item_id'))
    order_id = Column(Integer, ForeignKey('orders.order_id'))
    num_stars = Column(Integer, nullable=False)
    description = Column(String(255))
    __table_args__ = (PrimaryKeyConstraint('item_id', 'order_id'),)