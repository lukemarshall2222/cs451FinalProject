FROM python:3.11
EXPOSE 5000
WORKDIR /flask_app
COPY requirements.txt /flask_app
RUN pip install -r requirements.txt
COPY . /flask_app
CMD ["flask", "run", "--host", "0.0.0.0"]